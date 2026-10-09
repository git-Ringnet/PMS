<?php

namespace Tests\Feature;

use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\BranchRolePermission;
use App\Models\UserBranchPosition;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Http\Middleware\RequireFrontDeskInvoiceMutation;
use App\Http\Middleware\RequirePermission;
use App\Services\CheckoutRoleConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BookingRoleConfigCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private CheckoutRoleConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        SystemBranch::query()->firstOrCreate(['id' => 1], ['code' => 'TEST1', 'name' => 'Test branch 1']);
        SystemBranch::query()->firstOrCreate(['id' => 2], ['code' => 'TEST2', 'name' => 'Test branch 2']);
        $this->service = app(CheckoutRoleConfigService::class);
    }

    public function test_sale_inhouse_edit_config_is_opt_in_and_strictly_one(): void
    {
        $this->assertFalse($this->service->allowSaleInhouseRateDeparture(1));

        HotelConfig::updateOrCreate(['name' => 'AllowReserUpdateRate_DeptDateRoomInhouse'], ['value' => 'yes']);
        $this->assertFalse($this->service->allowSaleInhouseRateDeparture(1));

        HotelConfig::where('name', 'AllowReserUpdateRate_DeptDateRoomInhouse')->update(['value' => '1']);
        $this->assertTrue($this->service->allowSaleInhouseRateDeparture(1));
    }

    public function test_dnm_owner_fallback_and_exact_role_are_additional_to_edit_permission(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->grantPermission($owner, 'fo.booking.edit', 1);
        $this->grantPermission($other, 'fo.booking.edit', 1);

        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => '']);
        $role = Role::create(['code' => 'frontdesk', 'name' => 'Front desk']);
        $other->roles()->attach($role->id, ['system_branch_id' => 1]);

        $this->assertTrue($this->service->canOpenDoNotMove($owner, 1, $owner->id));
        $this->assertFalse($this->service->canOpenDoNotMove($other, 1, $owner->id));

        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'frontdesk']);
        $this->assertTrue($this->service->canOpenDoNotMove($other, 1, $owner->id));
        $this->assertFalse($this->service->canOpenDoNotMove($other, 2, $owner->id));
    }

    public function test_legacy_unlock_username_config_does_not_grant_dnm_role_access(): void
    {
        $user = User::factory()->create(['username' => 'legacy-unlocker']);
        $this->grantPermission($user, 'fo.booking.edit', 1);
        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockDoNotMove'], ['value' => 'legacy-unlocker']);

        $this->assertFalse($this->service->canOpenDoNotMove($user, 1, 99999));
    }

    public function test_checkout_booking_role_requires_exact_role_and_existing_edit_permission(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, 'fo.booking.edit', 1);
        $role = Role::create(['code' => 'checkout_editor', 'name' => 'Checkout editor']);
        $user->roles()->attach($role->id, ['system_branch_id' => 1]);
        HotelConfig::updateOrCreate(['name' => 'RoleUserUpdateCheckoutBooking'], ['value' => 'checkout_editor']);

        $this->assertTrue($this->service->canUpdateCheckedOutBooking($user, 1));
        $this->assertFalse($this->service->canUpdateCheckedOutBooking($user, 2));

        $withoutBasePermission = User::factory()->create();
        $withoutBasePermission->roles()->attach($role->id, ['system_branch_id' => 1]);
        $this->assertFalse($this->service->canUpdateCheckedOutBooking($withoutBasePermission, 1));

        $nearMatchUser = User::factory()->create();
        $this->grantPermission($nearMatchUser, 'fo.booking.edit', 1);
        $nearMatchRole = Role::create(['code' => 'checkout_editor_extra', 'name' => 'Near-match editor']);
        $nearMatchUser->roles()->attach($nearMatchRole->id, ['system_branch_id' => 1]);
        $this->assertFalse($this->service->canUpdateCheckedOutBooking($nearMatchUser, 1));

        HotelConfig::updateOrCreate(['name' => 'RoleUserUpdateCheckoutBooking'], ['value' => '']);
        $this->assertFalse($this->service->canUpdateCheckedOutBooking($user, 1));
    }

    public function test_new_pms_position_role_grants_s11_s12_only_at_its_branch(): void
    {
        $user = User::factory()->create();
        $role = $this->assignPositionRole($user, 1, 'PMS', 'booking3_position_manager');
        $this->grantBranchRolePermission($role, 1, 'fo.booking.edit');
        HotelConfig::updateOrCreate(['name' => 'RoleUserUpdateCheckoutBooking'], ['value' => 'booking3_position_manager']);
        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'booking3_position_manager']);

        $this->assertTrue($this->service->canUpdateCheckedOutBooking($user, 1));
        $this->assertFalse($this->service->canUpdateCheckedOutBooking($user, 2));
        $this->assertTrue($this->service->canOpenDoNotMove($user, 1, 99999));
        $this->assertFalse($this->service->canOpenDoNotMove($user, 2, 99999));
    }

    public function test_new_pms_assignment_does_not_fall_back_to_legacy_role_for_another_branch(): void
    {
        $user = User::factory()->create();
        $pmsRole = $this->assignPositionRole($user, 2, 'PMS', 'other_branch_role');
        $this->grantBranchRolePermission($pmsRole, 2, 'fo.booking.edit');

        $legacyRole = Role::create(['code' => 'booking3_legacy_allowed', 'name' => 'Legacy allowed']);
        $user->roles()->attach($legacyRole->id, ['system_branch_id' => 1]);
        $legacyPermission = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'application_code' => 'PMS']);
        $legacyRole->permissions()->syncWithoutDetaching([$legacyPermission->id]);
        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'booking3_legacy_allowed']);

        $this->assertFalse($this->service->canOpenDoNotMove($user, 1, 99999));
        $this->assertFalse($this->service->canOpenDoNotMove($user, 2, 99999));
    }

    public function test_position_role_from_another_application_does_not_grant_a_pms_s12_role(): void
    {
        $user = User::factory()->create();
        $this->assignPositionRole($user, 1, 'CRM', 'booking3_cross_app_role');
        $legacyEditRole = Role::create(['code' => 'booking3_cross_app_edit', 'name' => 'Legacy edit']);
        $user->roles()->attach($legacyEditRole->id, ['system_branch_id' => 1]);
        $editPermission = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'fo.booking.edit', 'application_code' => 'PMS']);
        $legacyEditRole->permissions()->syncWithoutDetaching([$editPermission->id]);
        HotelConfig::updateOrCreate(['name' => 'RoleUserOpenDoNotMove'], ['value' => 'booking3_cross_app_role']);

        $this->assertTrue($user->hasPermission('fo.booking.edit', 1));
        $this->assertFalse($this->service->canOpenDoNotMove($user, 1, 99999));
    }

    public function test_housekeeping_write_permissions_allow_hk_poster_but_not_sale_viewer(): void
    {
        $viewer = User::factory()->create();
        $this->grantPermission($viewer, 'fo.booking.edit', 1);
        $this->grantPermission($viewer, 'fo.service.view', 1);

        $this->actingAs($viewer)
            ->postJson('/api/booking-room-services/post-housekeeping-bill?module=fo&readOnly=false', [
                'booking_room_id' => 'G0000001', 'readOnly' => false, 'current_module' => 'FO',
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson('/api/housekeeping/service-bills/1/cancel?module=fo&readOnly=false', [
                'reason' => 'unauthorized', 'readOnly' => false, 'current_module' => 'FO',
            ])
            ->assertForbidden();

        $hkPoster = User::factory()->create();
        $this->grantPermission($hkPoster, 'hk.service.bill', 1);
        $this->actingAs($hkPoster)
            ->postJson('/api/booking-room-services/post-housekeeping-bill?module=fo&readOnly=false', [])
            ->assertUnprocessable();

        $foServiceEditor = User::factory()->create();
        $this->grantPermission($foServiceEditor, 'fo.service.add', 1);
        $this->actingAs($foServiceEditor)
            ->postJson('/api/booking-room-services/post-housekeeping-bill?module=fo&readOnly=false', [])
            ->assertUnprocessable();

        $hkManager = User::factory()->create();
        $this->grantPermission($hkManager, 'hk.service.delete', 1);
        $this->actingAs($hkManager)
            ->postJson('/api/housekeeping/service-bills/1/cancel?module=fo&readOnly=false', [])
            ->assertUnprocessable();
    }

    public function test_checkout_invoice_capability_requires_an_invoice_mutation_permission(): void
    {
        $viewer = User::factory()->create();
        $mutator = User::factory()->create();
        $this->grantPermission($viewer, 'fo.booking.edit', 1);
        $this->grantPermission($mutator, 'fo.payment.create', 1);

        $this->assertFalse($this->service->canMutateCheckoutInvoice($viewer, 1));
        $this->assertTrue($this->service->canMutateCheckoutInvoice($mutator, 1));
        $this->assertFalse($this->service->canMutateCheckoutInvoice($mutator, 2));
    }

    public function test_shared_sale_service_writes_keep_granular_access_while_checkout_writes_require_frontdesk_view(): void
    {
        $saleServiceEditor = User::factory()->create();
        $this->grantPermission($saleServiceEditor, 'fo.service.add', 1);

        $serviceRequest = Request::create('/api/booking-rooms/1/services', 'POST');
        $serviceRequest->setUserResolver(fn () => $saleServiceEditor);
        $serviceRequest->attributes->set('_branch_id', 1);
        $serviceResponse = app(RequirePermission::class)->handle(
            $serviceRequest,
            fn () => response('service accepted'),
            'fo.service.add'
        );
        $this->assertSame(200, $serviceResponse->getStatusCode());

        $saleCheckoutUser = User::factory()->create();
        $this->grantPermission($saleCheckoutUser, 'fo.checkout', 1);
        $checkoutRequest = Request::create('/api/bookings/1/checkout', 'POST');
        $checkoutRequest->setUserResolver(fn () => $saleCheckoutUser);
        $checkoutRequest->attributes->set('_branch_id', 1);
        $checkoutResponse = app(RequirePermission::class)->handle(
            $checkoutRequest,
            fn ($request) => app(RequireFrontDeskInvoiceMutation::class)->handle(
                $request,
                fn () => response('checkout accepted')
            ),
            'fo.checkout'
        );
        $this->assertSame(403, $checkoutResponse->getStatusCode());

        $noPostRequest = Request::create('/api/bookings/1/no-post', 'PATCH', ['no_post' => true]);
        $noPostRequest->setUserResolver(fn () => $saleCheckoutUser);
        $noPostRequest->attributes->set('_branch_id', 1);
        $noPostResponse = app(RequirePermission::class)->handle(
            $noPostRequest,
            fn ($request) => app(RequireFrontDeskInvoiceMutation::class)->handle(
                $request,
                fn () => response('no-post accepted')
            ),
            'fo.booking.edit'
        );
        $this->assertSame(403, $noPostResponse->getStatusCode());

        $this->grantPermission($saleCheckoutUser, 'fo.frontdesk.view', 1);
        $checkoutRequest = Request::create('/api/bookings/1/checkout', 'POST');
        $checkoutRequest->setUserResolver(fn () => $saleCheckoutUser);
        $checkoutRequest->attributes->set('_branch_id', 1);
        $authorizedCheckoutResponse = app(RequirePermission::class)->handle(
            $checkoutRequest,
            fn ($request) => app(RequireFrontDeskInvoiceMutation::class)->handle(
                $request,
                fn () => response('checkout accepted')
            ),
            'fo.checkout'
        );
        $this->assertSame(200, $authorizedCheckoutResponse->getStatusCode());

        $routes = collect(Route::getRoutes());
        $serviceRoute = $routes->first(fn ($route) => $route->uri() === 'api/booking-rooms/{roomId}/services' && in_array('POST', $route->methods(), true));
        $paymentRoute = $routes->first(fn ($route) => $route->uri() === 'api/bookings/{bookingId}/payments' && in_array('POST', $route->methods(), true));
        $housekeepingPostRoute = $routes->first(fn ($route) => $route->uri() === 'api/booking-room-services/post-housekeeping-bill' && in_array('POST', $route->methods(), true));
        $checkoutRoute = $routes->first(fn ($route) => $route->uri() === 'api/bookings/{bookingId}/checkout' && in_array('POST', $route->methods(), true));
        $noPostRoute = $routes->first(fn ($route) => $route->uri() === 'api/bookings/{bookingId}/no-post' && in_array('PATCH', $route->methods(), true));

        $this->assertNotNull($serviceRoute);
        $this->assertNotNull($paymentRoute);
        $this->assertNotNull($housekeepingPostRoute);
        $this->assertNotNull($checkoutRoute);
        $this->assertNotNull($noPostRoute);
        $this->assertContains('permission:fo.service.add,fo.service.edit', $serviceRoute->gatherMiddleware());
        $this->assertContains('permission:fo.payment.create', $paymentRoute->gatherMiddleware());
        $this->assertNotContains(RequireFrontDeskInvoiceMutation::class, $serviceRoute->gatherMiddleware());
        $this->assertNotContains(RequireFrontDeskInvoiceMutation::class, $paymentRoute->gatherMiddleware());
        $this->assertNotContains(RequireFrontDeskInvoiceMutation::class, $housekeepingPostRoute->gatherMiddleware());
        $this->assertContains(RequireFrontDeskInvoiceMutation::class, $checkoutRoute->gatherMiddleware());
        $this->assertContains(RequireFrontDeskInvoiceMutation::class, $noPostRoute->gatherMiddleware());
    }

    private function assignPositionRole(User $user, int $branchId, string $applicationCode, string $roleCode): Role
    {
        $department = OrganizationDepartment::create([
            'code' => 'B3-' . strtoupper(substr(md5($roleCode . $branchId . $applicationCode), 0, 10)),
            'name' => 'Booking 3 test department',
        ]);
        $position = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'POS-' . strtoupper(substr(md5($roleCode . $branchId), 0, 8)),
            'name' => 'Booking 3 test position',
        ]);
        $role = Role::create(['code' => $roleCode, 'name' => $roleCode]);
        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $branchId,
            'application_code' => $applicationCode,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branchId,
            'application_code' => $applicationCode,
            'position_id' => $position->id,
        ]);
        return $role;
    }

    private function grantBranchRolePermission(Role $role, int $branchId, string $code): void
    {
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'application_code' => 'PMS']
        );
        BranchRolePermission::firstOrCreate([
            'system_branch_id' => $branchId,
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    private function grantPermission(User $user, string $code, int $branchId): void
    {
        $role = Role::create(['code' => 'role_' . $user->id . '_' . str_replace('.', '_', $code), 'name' => 'Test role']);
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'application_code' => 'PMS']
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id, ['system_branch_id' => $branchId]);
    }
}
