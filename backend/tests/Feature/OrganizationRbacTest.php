<?php

namespace Tests\Feature;

use App\Models\BranchRolePermission;
use App\Models\Department;
use App\Models\OrganizationDepartment;
use App\Models\Permission;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranchPosition;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_mot_nhan_vien_co_the_co_vi_tri_khac_nhau_tai_hai_chi_nhanh(): void
    {
        $department = OrganizationDepartment::firstOrCreate(
            ['code' => 'FO'],
            ['name' => 'Front Office / Lễ Tân', 'is_active' => true]
        );
        $fom = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'TEST_FOM',
            'name' => 'Trưởng bộ phận Lễ tân',
            'is_active' => true,
        ]);
        $fo = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'TEST_FO',
            'name' => 'Nhân viên Lễ tân',
            'is_active' => true,
        ]);
        $managerRole = Role::create(['code' => 'test_fom', 'name' => 'FOM', 'level' => 3, 'is_active' => true]);
        $staffRole = Role::create(['code' => 'test_fo', 'name' => 'FO', 'level' => 3, 'is_active' => true]);
        $branch1 = SystemBranch::create(['code' => 'TEST1', 'name' => 'Chi nhánh 1']);
        $branch2 = SystemBranch::create(['code' => 'TEST2', 'name' => 'Chi nhánh 2']);

        PositionBranchRole::create([
            'position_id' => $fom->id,
            'system_branch_id' => $branch1->id,
            'application_code' => 'PMS',
            'role_id' => $managerRole->id,
            'is_active' => true,
        ]);
        PositionBranchRole::create([
            'position_id' => $fo->id,
            'system_branch_id' => $branch2->id,
            'application_code' => 'PMS',
            'role_id' => $staffRole->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branch1->id,
            'application_code' => 'PMS',
            'position_id' => $fom->id,
        ]);
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branch2->id,
            'application_code' => 'PMS',
            'position_id' => $fo->id,
        ]);

        $this->assertDatabaseHas('user_branch_positions', [
            'user_id' => $user->id,
            'system_branch_id' => $branch1->id,
            'position_id' => $fom->id,
        ]);
        $this->assertDatabaseHas('user_branch_positions', [
            'user_id' => $user->id,
            'system_branch_id' => $branch2->id,
            'position_id' => $fo->id,
        ]);
        $this->assertSame(2, UserBranchPosition::where('user_id', $user->id)->count());
        $this->assertTrue($user->hasBranchAccess($branch1->id));
        $this->assertTrue($user->hasBranchAccess($branch2->id));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive_test',
            'password' => Hash::make('password123'),
            'is_active_user' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'inactive_test',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Tài khoản của bạn đã bị khóa hoặc ngừng sử dụng.',
        ]);
    }

    public function test_login_does_not_log_plain_password(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'username' => 'safe_test',
            'password' => Hash::make('secret_pass_999'),
            'is_active_user' => true,
        ]);

        $this->postJson('/api/login', [
            'username' => 'safe_test',
            'password' => 'secret_pass_999',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            if ($message === 'Login attempt') {
                return !isset($context['password']) && isset($context['username']);
            }
            return true;
        });
    }

    public function test_user_receives_different_permissions_across_two_branches(): void
    {
        $department = OrganizationDepartment::firstOrCreate(
            ['code' => 'FO'],
            ['name' => 'Front Office', 'is_active' => true]
        );
        $posLeader = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'POS_LEAD',
            'name' => 'Ca trưởng',
            'is_active' => true,
        ]);
        $posStaff = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'POS_STAFF',
            'name' => 'Nhân viên',
            'is_active' => true,
        ]);

        $roleLead = Role::create(['code' => 'role_lead', 'name' => 'Lead', 'level' => 3, 'is_active' => true]);
        $roleStaff = Role::create(['code' => 'role_staff', 'name' => 'Staff', 'level' => 3, 'is_active' => true]);

        $branchA = SystemBranch::create(['code' => 'BRA', 'name' => 'Chi nhánh A']);
        $branchB = SystemBranch::create(['code' => 'BRB', 'name' => 'Chi nhánh B']);

        PositionBranchRole::create([
            'position_id' => $posLeader->id,
            'system_branch_id' => $branchA->id,
            'application_code' => 'PMS',
            'role_id' => $roleLead->id,
            'is_active' => true,
        ]);
        PositionBranchRole::create([
            'position_id' => $posStaff->id,
            'system_branch_id' => $branchB->id,
            'application_code' => 'PMS',
            'role_id' => $roleStaff->id,
            'is_active' => true,
        ]);

        $permView = Permission::create([
            'code' => 'fo.booking.view',
            'name' => 'Xem Đặt phòng',
            'module' => 'fo',
            'screen_code' => 'fo.booking',
            'action' => 'view',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $permCreate = Permission::create([
            'code' => 'fo.booking.create',
            'name' => 'Tạo Đặt phòng',
            'module' => 'fo',
            'screen_code' => 'fo.booking',
            'action' => 'add',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);

        // Branch A role có cả View và Create
        BranchRolePermission::create([
            'role_id' => $roleLead->id,
            'system_branch_id' => $branchA->id,
            'permission_id' => $permView->id,
        ]);
        BranchRolePermission::create([
            'role_id' => $roleLead->id,
            'system_branch_id' => $branchA->id,
            'permission_id' => $permCreate->id,
        ]);

        // Branch B role CHỈ CÓ View
        BranchRolePermission::create([
            'role_id' => $roleStaff->id,
            'system_branch_id' => $branchB->id,
            'permission_id' => $permView->id,
        ]);

        $user = User::factory()->create(['is_active_user' => true]);
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branchA->id,
            'application_code' => 'PMS',
            'position_id' => $posLeader->id,
        ]);
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branchB->id,
            'application_code' => 'PMS',
            'position_id' => $posStaff->id,
        ]);

        // Kiểm tra quyền tại Branch A
        $permsBranchA = $user->allPermissions($branchA->id, 'PMS');
        $this->assertTrue($permsBranchA->contains('fo.booking.view'));
        $this->assertTrue($permsBranchA->contains('fo.booking.create'));

        // Kiểm tra quyền tại Branch B: có view nhưng KHÔNG có create
        $permsBranchB = $user->allPermissions($branchB->id, 'PMS');
        $this->assertTrue($permsBranchB->contains('fo.booking.view'));
        $this->assertFalse($permsBranchB->contains('fo.booking.create'));
    }

    public function test_add_edit_delete_implies_view_action(): void
    {
        $branch = SystemBranch::create(['code' => 'TEST_BR', 'name' => 'Chi nhánh Test']);
        $role = Role::create(['code' => 'hk_sup', 'name' => 'HK Supervisor', 'level' => 3, 'is_active' => true]);

        $permView = Permission::create([
            'code' => 'hk.room.view',
            'name' => 'Xem phòng',
            'module' => 'hk',
            'screen_code' => 'hk.room',
            'action' => 'view',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $permAdd = Permission::create([
            'code' => 'hk.room.add',
            'name' => 'Thêm phòng',
            'module' => 'hk',
            'screen_code' => 'hk.room',
            'action' => 'add',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);

        // Đăng nhập user có quyền system.user.manage để gọi sync
        $admin = User::factory()->create(['is_active_user' => true]);
        $superRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'level' => 1, 'is_active' => true]);
        $admin->roles()->attach($superRole->id);
        Sanctum::actingAs($admin);

        // Gọi API sync chỉ với permission ID của 'add' (KHÔNG gửi 'view')
        $response = $this->postJson("/api/roles/{$role->id}/branch-permissions/sync", [
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'permission_ids' => [$permAdd->id],
        ]);
        $response->assertStatus(200);

        // Quy tắc: Quyền Add tự động kéo theo View của cùng màn hình
        $this->assertDatabaseHas('branch_role_permissions', [
            'role_id' => $role->id,
            'system_branch_id' => $branch->id,
            'permission_id' => $permAdd->id,
        ]);
        $this->assertDatabaseHas('branch_role_permissions', [
            'role_id' => $role->id,
            'system_branch_id' => $branch->id,
            'permission_id' => $permView->id,
        ]);
    }

    public function test_super_admin_has_all_permissions_and_branch_access(): void
    {
        $superAdmin = User::factory()->create([
            'username' => 'root_admin',
            'is_active_user' => true,
        ]);

        $superRole = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Admin', 'level' => 1, 'is_active' => true]
        );
        $superAdmin->roles()->attach($superRole->id);

        Permission::create([
            'code' => 'system.all.manage',
            'name' => 'Manage System',
            'module' => 'system',
            'screen_code' => 'system',
            'action' => 'manage',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        Permission::create([
            'code' => 'fo.booking.view',
            'name' => 'View Booking',
            'module' => 'fo',
            'screen_code' => 'fo.booking',
            'action' => 'view',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);

        $branch = SystemBranch::create(['code' => 'BR_ANY', 'name' => 'Bất kỳ chi nhánh nào']);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->hasBranchAccess($branch->id));

        $perms = $superAdmin->allPermissions($branch->id, 'PMS');
        $this->assertTrue($perms->contains('system.all.manage'));
        $this->assertTrue($perms->contains('fo.booking.view'));
    }

    public function test_change_password_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password_123'),
            'is_active_user' => true,
        ]);

        Sanctum::actingAs($user);

        // Sai mật khẩu hiện tại -> 422
        $resWrong = $this->postJson('/api/me/change-password', [
            'current_password' => 'wrong_pass',
            'new_password' => 'new_password_456',
            'new_password_confirmation' => 'new_password_456',
        ]);
        $resWrong->assertStatus(422);

        // Mật khẩu mới không khớp confirmation -> 422
        $resMismatch = $this->postJson('/api/me/change-password', [
            'current_password' => 'old_password_123',
            'new_password' => 'new_password_456',
            'new_password_confirmation' => 'mismatched_password',
        ]);
        $resMismatch->assertStatus(422);

        // Đổi mật khẩu hợp lệ -> 200
        $resOk = $this->postJson('/api/me/change-password', [
            'current_password' => 'old_password_123',
            'new_password' => 'new_password_456',
            'new_password_confirmation' => 'new_password_456',
        ]);
        $resOk->assertStatus(200);
        $resOk->assertJson(['success' => true]);

        // Verify password has been updated in database
        $user->refresh();
        $this->assertTrue(Hash::check('new_password_456', $user->password));
    }

    public function test_fallback_to_legacy_user_roles_if_no_branch_positions(): void
    {
        $role = Role::create(['code' => 'legacy_fom', 'name' => 'Legacy FOM', 'level' => 3, 'is_active' => true]);
        $perm = Permission::create([
            'code' => 'fo.report.view',
            'name' => 'Xem báo cáo',
            'module' => 'fo',
            'screen_code' => 'fo.report',
            'action' => 'view',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $role->permissions()->attach($perm->id);

        $branch = SystemBranch::create(['code' => 'LEGACY_BR', 'name' => 'Chi nhánh Legacy']);

        $user = User::factory()->create(['is_active_user' => true]);
        // Gán role theo schema cũ (user_roles) có system_branch_id
        $user->roles()->attach($role->id, ['system_branch_id' => $branch->id]);

        // User chưa có user_branch_positions -> fallback về user_roles
        $perms = $user->allPermissions($branch->id, 'PMS');
        $this->assertTrue($perms->contains('fo.report.view'), 'Phải fallback về user_roles nếu chưa có vị trí công việc chi nhánh');
    }

    public function test_empty_permissions_at_branch_does_not_fallback_to_legacy_roles(): void
    {
        $legacyRole = Role::create(['code' => 'danger_role', 'name' => 'Danger Role', 'level' => 3, 'is_active' => true]);
        $dangerPerm = Permission::create([
            'code' => 'fo.danger.delete',
            'name' => 'Xóa dữ liệu nhạy cảm',
            'module' => 'fo',
            'screen_code' => 'fo.danger',
            'action' => 'delete',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $legacyRole->permissions()->attach($dangerPerm->id);

        $branch = SystemBranch::create(['code' => 'SECURE_BR', 'name' => 'Chi nhánh bảo mật']);

        $department = OrganizationDepartment::firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security', 'is_active' => true]
        );
        $position = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'SEC_TRAINEE',
            'name' => 'Thực tập sinh bảo mật',
            'is_active' => true,
        ]);

        $emptyRole = Role::create(['code' => 'empty_role', 'name' => 'Empty Role', 'level' => 3, 'is_active' => true]);

        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'role_id' => $emptyRole->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active_user' => true]);
        // Gán legacy role có quyền nguy hiểm
        $user->roles()->attach($legacyRole->id, ['system_branch_id' => $branch->id]);

        // Gán vị trí tại chi nhánh nhưng vị trí này có role rỗng (0 quyền)
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'position_id' => $position->id,
        ]);

        // Kiểm tra quyền: Phải trả về rỗng và KHÔNG được fallback về legacy role để nhận quyền nguy hiểm
        $perms = $user->allPermissions($branch->id, 'PMS');
        $this->assertFalse($perms->contains('fo.danger.delete'), 'Quyền rỗng tại chi nhánh KHÔNG ĐƯỢC fallback về legacy roles gây leo thang quyền');
        $this->assertTrue($perms->isEmpty());
    }

    public function test_store_screen_prefixes_non_pms_application_codes(): void
    {
        $admin = User::factory()->create(['is_active_user' => true]);
        $adminPerm = Permission::create([
            'code' => 'system.user.manage',
            'name' => 'Manage users',
            'module' => 'SYSTEM',
            'screen_code' => 'system.user',
            'action' => 'manage',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $adminRole = Role::create(['code' => 'admin_role', 'name' => 'Admin Role', 'level' => 1, 'is_active' => true]);
        $adminRole->permissions()->attach($adminPerm->id);
        $admin->roles()->attach($adminRole->id);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/permission-screens', [
            'application_code' => 'POS',
            'module' => 'order',
            'screen_code' => 'table_order',
            'screen_name' => 'Đặt bàn POS',
            'screen_type' => 'screen',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('permissions', [
            'code' => 'pos.order.table_order.view',
            'application_code' => 'POS',
            'screen_code' => 'order.table_order',
        ]);
    }

    public function test_sync_warehouses_endpoint_persists_assignments(): void
    {
        $admin = User::factory()->create(['is_active_user' => true]);
        $adminPerm = Permission::create([
            'code' => 'system.user.manage',
            'name' => 'Manage users',
            'module' => 'SYSTEM',
            'screen_code' => 'system.user',
            'action' => 'manage',
            'application_code' => 'PMS',
            'is_active' => true,
        ]);
        $adminRole = Role::create(['code' => 'super_admin_warehouse', 'name' => 'Admin Role', 'level' => 1, 'is_active' => true]);
        $adminRole->permissions()->attach($adminPerm->id);
        $admin->roles()->attach($adminRole->id);

        Sanctum::actingAs($admin);

        $branch = SystemBranch::create([
            'code' => 'WH_BR',
            'name' => 'Branch Warehouse',
            'db_connection' => 'sqlite',
            'is_active' => true,
        ]);
        $targetUser = User::factory()->create(['is_active_user' => true]);
        $department = OrganizationDepartment::create(['code' => 'WH', 'name' => 'Warehouse', 'is_active' => true]);
        $position = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'WH_TEST',
            'name' => 'Warehouse tester',
            'is_active' => true,
        ]);
        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        UserBranchPosition::create([
            'user_id' => $targetUser->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'position_id' => $position->id,
        ]);
        $warehouse1 = Warehouse::create(['name' => 'Warehouse 1', 'is_active' => true]);
        $warehouse2 = Warehouse::create(['name' => 'Warehouse 2', 'is_active' => true]);

        $res = $this->postJson("/api/users/{$targetUser->id}/warehouses/sync", [
            'warehouses' => [
                ['system_branch_id' => $branch->id, 'warehouse_id' => $warehouse1->id],
                ['system_branch_id' => $branch->id, 'warehouse_id' => $warehouse2->id],
            ],
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_warehouse_permissions', [
            'user_id' => $targetUser->id,
            'system_branch_id' => $branch->id,
            'warehouse_id' => $warehouse1->id,
        ]);
        $this->assertDatabaseHas('user_warehouse_permissions', [
            'user_id' => $targetUser->id,
            'system_branch_id' => $branch->id,
            'warehouse_id' => $warehouse2->id,
        ]);
    }

    public function test_permission_is_resolved_by_application_without_hardcoded_pms(): void
    {
        $department = OrganizationDepartment::create(['code' => 'APP', 'name' => 'Application', 'is_active' => true]);
        $position = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'APP_USER',
            'name' => 'Application user',
            'is_active' => true,
        ]);
        $branch = SystemBranch::create(['code' => 'APP_BR', 'name' => 'Application branch']);
        $role = Role::create(['code' => 'pos_operator', 'name' => 'POS operator', 'level' => 3, 'is_active' => true]);
        $permission = Permission::create([
            'code' => 'pos.order.create',
            'name' => 'Create POS order',
            'module' => 'ORDER',
            'screen_code' => 'order',
            'action' => 'add',
            'application_code' => 'POS',
            'is_active' => true,
        ]);
        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'POS',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        BranchRolePermission::create([
            'system_branch_id' => $branch->id,
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
        $user = User::factory()->create();
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'POS',
            'position_id' => $position->id,
        ]);

        $this->assertTrue($user->hasPermission('pos.order.create', $branch->id));
        $this->assertTrue($user->allPermissionsForBranch($branch->id)->contains('pos.order.create'));
        $this->assertFalse($user->allPermissions($branch->id, 'PMS')->contains('pos.order.create'));
    }

    public function test_historical_date_permission_comes_from_assigned_branch_role(): void
    {
        $department = OrganizationDepartment::create(['code' => 'HIS', 'name' => 'Historical', 'is_active' => true]);
        $position = Position::create([
            'organization_department_id' => $department->id,
            'code' => 'HIS_USER',
            'name' => 'Historical user',
            'is_active' => true,
        ]);
        $branch = SystemBranch::create(['code' => 'HIS_BR', 'name' => 'Historical branch']);
        $role = Role::create([
            'code' => 'historical_operator',
            'name' => 'Historical operator',
            'level' => 3,
            'is_active' => true,
            'allow_historical_date_actions' => true,
        ]);
        PositionBranchRole::create([
            'position_id' => $position->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserBranchPosition::create([
            'user_id' => $user->id,
            'system_branch_id' => $branch->id,
            'application_code' => 'PMS',
            'position_id' => $position->id,
        ]);

        $this->assertTrue($user->canPerformHistoricalDateActions($branch->id));
        $role->update(['allow_historical_date_actions' => false]);
        $this->assertFalse($user->canPerformHistoricalDateActions($branch->id));
    }

    public function test_forced_password_change_blocks_business_api_until_password_is_changed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password_123'),
            'must_change_password' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/modules')->assertStatus(423)->assertJson([
            'must_change_password' => true,
        ]);
        $this->getJson('/api/me')->assertOk();
        $this->postJson('/api/me/change-password', [
            'current_password' => 'old_password_123',
            'new_password' => 'new_password_456',
            'new_password_confirmation' => 'new_password_456',
        ])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_organization_imports_departments_from_legacy_department_table(): void
    {
        $admin = User::factory()->create();
        $superRole = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Admin', 'level' => 1, 'is_active' => true]
        );
        $admin->roles()->attach($superRole->id);
        Sanctum::actingAs($admin);
        Department::create([
            'code' => 'QA',
            'name' => 'Quality Assurance',
            'phone' => '0123456789',
            'show' => 1,
        ]);

        $this->getJson('/api/organization')->assertOk();
        $this->assertDatabaseHas('organization_departments', [
            'code' => 'QA',
            'name' => 'Quality Assurance',
            'phone' => '0123456789',
            'is_active' => true,
        ]);
    }
}
