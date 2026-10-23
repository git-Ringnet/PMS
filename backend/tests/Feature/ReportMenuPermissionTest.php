<?php

namespace Tests\Feature;

use App\Models\BranchRolePermission;
use App\Models\Permission;
use App\Models\ReportDataSource;
use App\Models\ReportDefinition;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Services\Reports\ReportPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 327: cấu hình menu báo cáo theo module (nhóm, thứ tự kéo thả).
 * Dòng 328: phân quyền xem từng báo cáo theo vai trò.
 */
class ReportMenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;
    private User $staff;
    private User $admin;
    private ReportDefinition $arrival;
    private ReportDefinition $departure;
    private ReportDefinition $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        // Bỏ các báo cáo do migration tạo để kiểm soát dữ liệu
        ReportDefinition::query()->delete();

        $this->role = Role::create(['code' => 'fo_test', 'name' => 'Lễ tân test', 'level' => 3, 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->staff->roles()->attach($this->role->id);

        $superAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 1, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($superAdmin->id);

        $source = ReportDataSource::create([
            'code' => 'TEST_SOURCE', 'name' => 'Test', 'schema_name' => 'test', 'object_name' => 'sp_test',
        ]);
        $make = fn (string $code, string $name, string $group, array $locations, int $itemOrder) => ReportDefinition::create([
            'code' => $code, 'name' => $name, 'group' => $group, 'report_data_source_id' => $source->id,
            'is_active' => true, 'show_in_menu' => true, 'menu_locations' => $locations,
            'menu_group_order' => $group === 'Báo cáo khách' ? 1 : 2, 'menu_item_order' => $itemOrder,
        ]);

        // Vai trò đã có quyền khác trước khi tạo báo cáo -> báo cáo mới được cấp sẵn quyền xem
        $other = Permission::firstOrCreate(['code' => 'fo.booking.view'], ['name' => 'Xem booking', 'module' => 'FO', 'application_code' => 'PMS']);
        $this->role->permissions()->attach($other->id);

        $this->arrival = $make('ARRIVAL_TEST', 'Báo cáo khách đến', 'Báo cáo khách', ['reservation', 'frontdesk'], 1);
        $this->departure = $make('DEPARTURE_TEST', 'Báo cáo khách đi', 'Báo cáo khách', ['reservation'], 2);
        $this->revenue = $make('REVENUE_TEST', 'Báo cáo doanh thu', 'Doanh thu', ['reservation'], 1);
        app(ReportPermissionService::class)->ensurePermissions(ReportDefinition::all());
    }

    private function navigation(User $user, string $location): array
    {
        return $this->actingAs($user)->getJson('/api/report-menu/navigation')->assertOk()->json("data.{$location}.items");
    }

    private function names(array $items): array
    {
        return array_map(fn ($item) => $item['type'] === 'group'
            ? [$item['name'] => array_column($item['children'], 'name')]
            : $item['name'], $items);
    }

    public function test_each_report_gets_a_view_permission_granted_to_existing_roles(): void
    {
        $permission = Permission::where('code', 'report.arrival_test.view')->first();

        $this->assertNotNull($permission);
        $this->assertSame('REPORT', $permission->module);
        $this->assertSame('Báo cáo khách đến', $permission->screen_name);
        $this->assertTrue($this->staff->hasPermission('report.arrival_test.view'));
    }

    public function test_default_menu_groups_reports_like_before(): void
    {
        $this->assertSame([
            ['Báo cáo khách' => ['Báo cáo khách đến', 'Báo cáo khách đi']],
            ['Doanh thu' => ['Báo cáo doanh thu']],
        ], $this->names($this->navigation($this->admin, 'reservation')));

        $this->assertSame([['Báo cáo khách' => ['Báo cáo khách đến']]], $this->names($this->navigation($this->admin, 'frontdesk')));
    }

    public function test_saved_tree_controls_order_and_module_membership(): void
    {
        $this->actingAs($this->admin)->putJson('/api/report-menu/reservation', ['tree' => [
            ['type' => 'report', 'report_id' => $this->revenue->id],
            ['type' => 'group', 'name' => 'Khách', 'children' => [
                ['report_id' => $this->departure->id],
                ['report_id' => $this->arrival->id],
            ]],
            ['type' => 'group', 'name' => 'Nhóm trống', 'children' => []],
        ]])->assertOk();

        // Nhóm trống không hiện trên menu
        $this->assertSame([
            'Báo cáo doanh thu',
            ['Khách' => ['Báo cáo khách đi', 'Báo cáo khách đến']],
        ], $this->names($this->navigation($this->admin, 'reservation')));

        // Gỡ "khách đi" khỏi Đăng ký phòng, thêm "doanh thu" vào Buồng phòng
        $this->actingAs($this->admin)->putJson('/api/report-menu/reservation', ['tree' => [
            ['type' => 'group', 'name' => 'Khách', 'children' => [['report_id' => $this->arrival->id]]],
            ['type' => 'report', 'report_id' => $this->revenue->id],
        ]])->assertOk();
        $this->actingAs($this->admin)->putJson('/api/report-menu/housekeeping', ['tree' => [
            ['type' => 'report', 'report_id' => $this->revenue->id],
        ]])->assertOk();

        $this->assertSame([], $this->departure->fresh()->menu_locations);
        $this->assertFalse($this->departure->fresh()->show_in_menu);
        $this->assertEqualsCanonicalizing(['reservation', 'housekeeping'], $this->revenue->fresh()->menu_locations);
        $this->assertSame(['Báo cáo doanh thu'], $this->names($this->navigation($this->admin, 'housekeeping')));
    }

    public function test_new_report_is_appended_to_group_with_same_name(): void
    {
        $this->actingAs($this->admin)->putJson('/api/report-menu/reservation', ['tree' => [
            ['type' => 'group', 'name' => 'Doanh thu', 'children' => [['report_id' => $this->revenue->id]]],
            ['type' => 'group', 'name' => 'Báo cáo khách', 'children' => [['report_id' => $this->arrival->id], ['report_id' => $this->departure->id]]],
        ]])->assertOk();

        ReportDefinition::create([
            'code' => 'INHOUSE_TEST', 'name' => 'Báo cáo khách ở', 'group' => 'Báo cáo khách',
            'report_data_source_id' => $this->arrival->report_data_source_id, 'is_active' => true,
            'show_in_menu' => true, 'menu_locations' => ['reservation'],
        ]);

        $this->assertSame([
            ['Doanh thu' => ['Báo cáo doanh thu']],
            ['Báo cáo khách' => ['Báo cáo khách đến', 'Báo cáo khách đi', 'Báo cáo khách ở']],
        ], $this->names($this->navigation($this->admin, 'reservation')));
    }

    public function test_saving_duplicate_report_or_group_is_rejected(): void
    {
        $this->actingAs($this->admin)->putJson('/api/report-menu/reservation', ['tree' => [
            ['type' => 'report', 'report_id' => $this->revenue->id],
            ['type' => 'group', 'name' => 'Khách', 'children' => [['report_id' => $this->revenue->id]]],
        ]])->assertStatus(422);

        $this->actingAs($this->admin)->putJson('/api/report-menu/reservation', ['tree' => [
            ['type' => 'group', 'name' => 'Khách', 'children' => []],
            ['type' => 'group', 'name' => 'khách', 'children' => []],
        ]])->assertStatus(422);

        $this->actingAs($this->admin)->putJson('/api/report-menu/unknown', ['tree' => []])->assertNotFound();
    }

    public function test_only_admin_or_config_permission_can_change_report_menu(): void
    {
        $this->actingAs($this->staff)->getJson('/api/report-menu')->assertForbidden();
        $this->actingAs($this->staff)->putJson('/api/report-menu/reservation', ['tree' => []])->assertForbidden();

        $permission = Permission::firstOrCreate(['code' => 'config.hotel.edit'], ['name' => 'Sửa cấu hình', 'module' => 'CONFIG', 'application_code' => 'PMS']);
        $this->role->permissions()->attach($permission->id);
        $this->actingAs($this->staff)->getJson('/api/report-menu')->assertOk();
    }

    public function test_user_only_sees_and_opens_reports_they_are_allowed_to_view(): void
    {
        $revenuePermission = Permission::where('code', ReportPermissionService::permissionCode($this->revenue))->first();
        $this->role->permissions()->detach($revenuePermission->id);

        $codes = array_column($this->actingAs($this->staff)->getJson('/api/report-definitions?active_only=1')->assertOk()->json('data'), 'code');
        $this->assertEqualsCanonicalizing(['ARRIVAL_TEST', 'DEPARTURE_TEST'], $codes);
        $this->assertSame([['Báo cáo khách' => ['Báo cáo khách đến', 'Báo cáo khách đi']]], $this->names($this->navigation($this->staff, 'reservation')));

        $this->actingAs($this->staff)->getJson("/api/report-definitions/{$this->revenue->id}")->assertForbidden();
        $this->actingAs($this->staff)->postJson("/api/report-definitions/{$this->revenue->id}/execute", [])->assertForbidden();
        $this->actingAs($this->staff)->getJson("/api/report-definitions/{$this->arrival->id}")->assertOk();

        // Super admin luôn xem được
        $this->actingAs($this->admin)->getJson("/api/report-definitions/{$this->revenue->id}")->assertOk();
    }

    public function test_role_access_is_saved_per_branch(): void
    {
        $branch = SystemBranch::create(['code' => 'HKT1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        $url = "/api/report-definitions/{$this->revenue->id}/role-access?system_branch_id={$branch->id}";

        $roles = $this->actingAs($this->admin)->getJson($url)->assertOk()->json('data.roles');
        $this->assertNotContains('super_admin', array_column($roles, 'code'));
        $this->assertFalse(collect($roles)->firstWhere('id', $this->role->id)['granted']);

        $this->actingAs($this->admin)->putJson($url, ['role_ids' => [$this->role->id]])->assertOk()
            ->assertJsonPath('data.roles.0.granted', true);

        $permissionId = Permission::where('code', 'report.revenue_test.view')->value('id');
        $this->assertTrue(BranchRolePermission::where([
            'system_branch_id' => $branch->id, 'role_id' => $this->role->id, 'permission_id' => $permissionId,
        ])->exists());

        $this->actingAs($this->admin)->putJson($url, ['role_ids' => []])->assertOk();
        $this->assertSame(0, BranchRolePermission::where('permission_id', $permissionId)->count());

        // Nhân viên không có quyền quản trị người dùng thì không sửa được
        $this->actingAs($this->staff)->putJson($url, ['role_ids' => [$this->role->id]])->assertForbidden();
    }
}
