<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockNightAuditRequests;
use App\Models\HotelConfig;
use App\Models\OrganizationDepartment;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranchPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomStatusPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $frontdeskUser;
    protected User $otherUser;
    protected Room $room;
    protected SystemBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(BlockNightAuditRequests::class);

        config(['database_domains.branch_connections' => [
            'HKT1' => 'sqlite',
        ]]);

        $this->branch = SystemBranch::firstOrCreate(
            ['code' => 'HKT1'],
            ['name' => 'Chi nhánh 1', 'is_active' => true]
        );

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@pms.com',
            'password' => bcrypt('password'),
            'job_title' => 'Tổng giám đốc',
            'job_title_code' => 'RL001',
            'primary_branch_id' => $this->branch->id,
            'is_active_user' => true,
        ]);

        $this->frontdeskUser = User::create([
            'name' => 'FO Staff',
            'username' => 'fostaff',
            'email' => 'fo@pms.com',
            'password' => bcrypt('password'),
            'job_title' => 'Nhân viên lễ tân',
            'job_title_code' => 'FO_STAFF',
            'primary_branch_id' => $this->branch->id,
            'is_active_user' => true,
        ]);

        $this->otherUser = User::create([
            'name' => 'Security Staff',
            'username' => 'security',
            'email' => 'sec@pms.com',
            'password' => bcrypt('password'),
            'job_title' => 'Bảo vệ',
            'job_title_code' => 'SEC_STAFF',
            'primary_branch_id' => $this->branch->id,
            'is_active_user' => true,
        ]);

        $roomClass = RoomClass::create([
            'name' => 'Standard',
            'code' => 'STD',
            'is_active' => true,
        ]);

        $roomForm = RoomForm::create([
            'name' => 'Single',
            'code' => 'SGL',
        ]);

        $this->room = Room::create([
            'room_number' => '101',
            'room_class_id' => $roomClass->id,
            'room_form_id' => $roomForm->id,
            'room_status_code' => 'vacant_ready',
            'floor' => '1',
            'is_active' => true,
            'is_clean' => true,
        ]);

        HotelConfig::updateOrCreate(
            ['name' => 'AllowChangeRoomStatusAtReception'],
            ['value' => '1']
        );
    }

    public function test_master_switch_off_disables_reception_status_change_even_if_role_matches()
    {
        HotelConfig::updateOrCreate(
            ['name' => 'AllowChangeRoomStatusAtReception'],
            ['value' => '0']
        );
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => 'FOM, fo_staff, FO']
        );

        // User matching role is denied because master switch is off (0)
        Sanctum::actingAs($this->frontdeskUser);
        $response = $this->getJson('/api/rooms/permissions?current_module=frontdesk');
        $response->assertStatus(200);
        $this->assertFalse($response->json('data.can_change_room_status'));

        // Super admin can still change
        Sanctum::actingAs($this->adminUser);
        $resAdmin = $this->getJson('/api/rooms/permissions?current_module=frontdesk');
        $resAdmin->assertStatus(200);
        $this->assertTrue($resAdmin->json('data.can_change_room_status'));
    }

    public function test_housekeeping_module_always_allowed()
    {
        Sanctum::actingAs($this->otherUser);

        $response = $this->getJson('/api/rooms/permissions?current_module=housekeeping');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.can_change_room_status'));
    }

    public function test_reservation_module_always_disallowed()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/rooms/permissions?current_module=reservation');

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.can_change_room_status'));
    }

    public function test_super_admin_can_always_change_room_status_at_reception()
    {
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => 'manager,fom']
        );

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/rooms/permissions?current_module=frontdesk');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.can_change_room_status'));
    }

    public function test_fallback_to_allow_change_room_status_config_when_role_config_empty()
    {
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => '']
        );

        HotelConfig::updateOrCreate(
            ['name' => 'AllowChangeRoomStatusAtReception'],
            ['value' => '1']
        );

        Sanctum::actingAs($this->otherUser);

        $response = $this->getJson('/api/rooms/permissions?current_module=frontdesk');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.can_change_room_status'));

        HotelConfig::updateOrCreate(
            ['name' => 'AllowChangeRoomStatusAtReception'],
            ['value' => '0']
        );

        $response2 = $this->getJson('/api/rooms/permissions?current_module=frontdesk');

        $response2->assertStatus(200);
        $this->assertFalse($response2->json('data.can_change_room_status'));
    }

    public function test_allowed_roles_restricts_unauthorized_user()
    {
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => 'FO, FOM, Lễ tân']
        );

        // frontdeskUser has job_title 'Nhân viên lễ tân' and 'FO_STAFF' -> matches
        Sanctum::actingAs($this->frontdeskUser);
        $resAllowed = $this->getJson('/api/rooms/permissions?current_module=frontdesk');
        $resAllowed->assertStatus(200);
        $this->assertTrue($resAllowed->json('data.can_change_room_status'));

        // otherUser has 'Bảo vệ' -> does not match
        Sanctum::actingAs($this->otherUser);
        $resDenied = $this->getJson('/api/rooms/permissions?current_module=frontdesk');
        $resDenied->assertStatus(200);
        $this->assertFalse($resDenied->json('data.can_change_room_status'));

        // API update status check
        $resUpdateDenied = $this->putJson("/api/rooms/{$this->room->id}/status", [
            'room_status_code' => 'vacant_dirty',
            'current_module' => 'frontdesk',
        ]);
        $resUpdateDenied->assertStatus(403);

        Sanctum::actingAs($this->frontdeskUser);
        $resUpdateAllowed = $this->putJson("/api/rooms/{$this->room->id}/status", [
            'room_status_code' => 'vacant_dirty',
            'current_module' => 'frontdesk',
        ]);
        $resUpdateAllowed->assertStatus(200);
    }

    public function test_allowed_roles_with_rbac_position_and_role_assignment()
    {
        $dept = OrganizationDepartment::create([
            'code' => 'FO_DEPT',
            'name' => 'Bộ phận Lễ tân',
            'is_active' => true,
        ]);

        $position = Position::create([
            'organization_department_id' => $dept->id,
            'code' => 'RECEPTIONIST',
            'name' => 'Tiếp tân',
            'is_active' => true,
        ]);

        $role = Role::create([
            'code' => 'frontdesk_operator',
            'name' => 'Vận hành lễ tân',
            'is_active' => true,
        ]);

        UserBranchPosition::create([
            'user_id' => $this->otherUser->id,
            'position_id' => $position->id,
            'system_branch_id' => $this->branch->id,
            'application_code' => 'PMS',
        ]);

        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $this->branch->id,
            'application_code' => 'PMS',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => 'frontdesk_operator']
        );

        Sanctum::actingAs($this->otherUser);

        $response = $this->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->getJson('/api/rooms/permissions?current_module=frontdesk');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.can_change_room_status'));
    }

    public function test_bulk_status_update_enforces_permission()
    {
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserAllowChangeRoomStatusAtReception'],
            ['value' => 'manager']
        );

        Sanctum::actingAs($this->otherUser);

        $response = $this->postJson('/api/rooms/bulk-status', [
            'room_ids' => [$this->room->id],
            'room_status_code' => 'vacant_clean',
            'current_module' => 'frontdesk',
        ]);

        $response->assertStatus(403);
    }
}
