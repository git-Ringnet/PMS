<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Checklist Hệ thống TC_HT_03..06 (sheet Hệ thống dòng 16-37). */
class SystemChecklistHt0306Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin HT']);
        $role = Role::firstOrCreate(['code' => 'ht_admin'], ['name' => 'HT Admin', 'level' => 1, 'is_active' => true]);
        foreach (['system.user.manage', 'system.user.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'SYSTEM']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    /** Dòng 32: đặt lại mật khẩu không bắt buộc 6 ký tự (giống mật khẩu khởi tạo khi tạo nhân viên). */
    public function test_row_32_admin_reset_password_accepts_short_password(): void
    {
        $staff = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($this->admin())
            ->postJson("/api/users/{$staff->id}/reset-password", ['password' => '123', 'password_confirmation' => '123'])
            ->assertOk();

        $this->assertTrue(Hash::check('123', $staff->fresh()->password));
        // Dòng 33: log ghi rõ thao tác đặt lại mật khẩu
        $this->assertSame(
            "* Đặt lại mật khẩu cho nhân viên '{$staff->name}' (Tên đăng nhập: {$staff->username})",
            \App\Models\ActivityLog::where('target_type', 'User')->where('target_id', (string) $staff->id)->value('description')
        );
    }

    /** Dòng 24: Copy phân quyền nhập mã đã tồn tại phải báo lỗi tiếng Việt rõ ràng (trước đây màn hình im lặng). */
    public function test_row_24_copy_role_with_existing_code_returns_clear_message(): void
    {
        $branch = \App\Models\SystemBranch::create(['code' => 'HT24', 'name' => 'Chi nhánh HT24']);
        $source = Role::create(['code' => 'fo_manager_ht', 'name' => 'Trưởng Lễ Tân', 'level' => 3, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson("/api/roles/{$source->id}/copy", [
                'code' => 'fo_manager_ht', 'name' => 'Bản sao', 'system_branch_id' => $branch->id, 'application_code' => 'PMS',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Mã quyền "fo_manager_ht" đã tồn tại, vui lòng nhập mã khác.');
    }

    public function test_row_24_copy_role_copies_all_branch_permissions(): void
    {
        $branch = \App\Models\SystemBranch::create(['code' => 'HT24B', 'name' => 'Chi nhánh HT24B']);
        $source = Role::create(['code' => 'src_ht24', 'name' => 'Nguồn', 'level' => 3, 'is_active' => true]);
        foreach (['fo.checkin', 'fo.checkout', 'fo.bill.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO', 'application_code' => 'PMS']);
            \App\Models\BranchRolePermission::create(['system_branch_id' => $branch->id, 'role_id' => $source->id, 'permission_id' => $permission->id]);
        }

        $response = $this->actingAs($this->admin())
            ->postJson("/api/roles/{$source->id}/copy", [
                'code' => 'copy_ht24', 'name' => 'Bản sao', 'system_branch_id' => $branch->id, 'application_code' => 'PMS',
            ])
            ->assertCreated();

        $this->assertSame(3, \App\Models\BranchRolePermission::where('role_id', $response->json('data.id'))->count());
    }

    /** Dòng 37: xuất nhật ký ra file Excel thật, mỗi cột một ô (trước đây CSV nên Excel dồn hết vào 1 ô). */
    public function test_row_37_activity_log_export_is_real_xlsx_with_one_value_per_cell(): void
    {
        $user = $this->admin();
        $permission = Permission::firstOrCreate(['code' => 'mgmt.activity_log'], ['name' => 'Lịch sử thao tác', 'module' => 'MGMT']);
        $user->roles()->first()->permissions()->syncWithoutDetaching([$permission->id]);

        $response = $this->actingAs($user)->post('/api/activity-logs/export', [
            'headers' => ['ID', 'Người dùng', 'Mô tả'],
            'rows' => [[7, 'thao', "Dòng 1, có dấu phẩy; chấm phẩy\nDòng 2"], [8, 'vy', 'Nội dung "A"']],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $file = tempnam(sys_get_temp_dir(), 'ht37') . '.xlsx';
        file_put_contents($file, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        @unlink($file);

        $this->assertSame(['ID', 'Người dùng', 'Mô tả'], [$sheet->getCell('A1')->getValue(), $sheet->getCell('B1')->getValue(), $sheet->getCell('C1')->getValue()]);
        $this->assertSame('thao', $sheet->getCell('B2')->getValue());
        $this->assertSame("Dòng 1, có dấu phẩy; chấm phẩy\nDòng 2", $sheet->getCell('C2')->getValue());
        $this->assertTrue($sheet->getStyle('C2')->getAlignment()->getWrapText());
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
    }

    /** Dòng 36: chi tiết thay đổi trả về bảng Trường / Trước / Sau dễ đọc, chỉ các trường thay đổi, bỏ trường kỹ thuật. */
    public function test_row_36_activity_log_returns_readable_change_rows(): void
    {
        $user = $this->admin();
        $permission = Permission::firstOrCreate(['code' => 'mgmt.activity_log'], ['name' => 'Lịch sử thao tác', 'module' => 'MGMT']);
        $user->roles()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        \App\Models\ActivityLog::create([
            'user_id' => $user->id, 'user_name' => $user->name, 'action' => 'Modify', 'module' => 'system', 'component' => 'Phân quyền Vai trò',
            'description' => 'x', 'created_at' => now(),
            'old_values' => ['name' => 'Lễ tân', 'is_active' => 1, 'arrival_date' => '2026-09-01', 'updated_at' => '2026-09-01 10:00:00', 'sort_order' => 1],
            'new_values' => ['name' => 'Lễ tân ca đêm', 'is_active' => 0, 'arrival_date' => '2026-09-01', 'updated_at' => '2026-09-02 10:00:00', 'sort_order' => 2],
        ]);

        $changes = $this->actingAs($user)->getJson('/api/activity-logs?per_page=5')->assertOk()->json('data.0.changes');

        $this->assertSame([
            ['field' => 'name', 'label' => 'Tên', 'before' => 'Lễ tân', 'after' => 'Lễ tân ca đêm'],
            ['field' => 'is_active', 'label' => 'Kích hoạt', 'before' => 'Có', 'after' => 'Không'],
        ], $changes);
    }

    /** Dòng 33: lưu ma trận quyền ghi MỘT dòng log bằng tên vai trò / chi nhánh / màn hình (không còn permission_id). */
    public function test_row_33_role_matrix_sync_logs_readable_summary(): void
    {
        $branch = \App\Models\SystemBranch::create(['code' => 'HT33', 'name' => 'Chi nhánh HT33']);
        $role = Role::create(['code' => 'fom_ht33', 'name' => 'Trưởng Lễ Tân', 'level' => 3, 'is_active' => true]);
        $checkout = Permission::create(['code' => 'ht33.checkout', 'name' => 'Trả phòng', 'screen_code' => 'ht33.checkout', 'screen_name' => 'Trả phòng', 'action' => 'view', 'module' => 'FO', 'application_code' => 'PMS']);
        $deposit = Permission::create(['code' => 'ht33.deposit', 'name' => 'Đặt cọc', 'screen_code' => 'ht33.deposit', 'screen_name' => 'Đặt cọc', 'action' => 'view', 'module' => 'FO', 'application_code' => 'PMS']);
        \App\Models\BranchRolePermission::create(['system_branch_id' => $branch->id, 'role_id' => $role->id, 'permission_id' => $deposit->id]);

        $this->actingAs($this->admin())
            ->postJson("/api/roles/{$role->id}/branch-permissions/sync", ['system_branch_id' => $branch->id, 'application_code' => 'PMS', 'permission_ids' => [$checkout->id]])
            ->assertOk();

        $logs = \App\Models\ActivityLog::where('target_type', 'Role')->where('target_id', (string) $role->id)->get();
        $this->assertCount(1, $logs);
        $text = $logs->first()->description;
        $this->assertStringContainsString("* Lưu phân quyền vai trò 'Trưởng Lễ Tân' - Chi nhánh: Chi nhánh HT33 - Ứng dụng: PMS", $text);
        $this->assertStringContainsString('• Cấp thêm (1): Trả phòng - Xem', $text);
        $this->assertStringContainsString('• Bỏ quyền (1): Đặt cọc - Xem', $text);
        $this->assertStringNotContainsString('permission_id', $text);
    }

    public function test_row_33_role_copy_logs_source_target_and_count(): void
    {
        $branch = \App\Models\SystemBranch::create(['code' => 'HT33C', 'name' => 'Chi nhánh HT33C']);
        $source = Role::create(['code' => 'src_ht33', 'name' => 'Trưởng Lễ Tân', 'level' => 3, 'is_active' => true]);
        $permission = Permission::create(['code' => 'ht33c.view', 'name' => 'Sơ đồ phòng', 'action' => 'view', 'module' => 'FO', 'application_code' => 'PMS']);
        \App\Models\BranchRolePermission::create(['system_branch_id' => $branch->id, 'role_id' => $source->id, 'permission_id' => $permission->id]);

        $this->actingAs($this->admin())->postJson("/api/roles/{$source->id}/copy", [
            'code' => 'fom_dem', 'name' => 'Lễ tân ca đêm', 'system_branch_id' => $branch->id, 'application_code' => 'PMS',
        ])->assertCreated();

        $text = \App\Models\ActivityLog::where('target_label', 'Lễ tân ca đêm')->value('description');
        $this->assertStringContainsString("Copy phân quyền từ vai trò 'Trưởng Lễ Tân' sang vai trò mới 'Lễ tân ca đêm' (Mã: fom_dem)", $text);
        $this->assertStringContainsString('• Số quyền đã sao chép: 1', $text);
    }

    public function test_row_33_position_application_log_names_branch_and_role(): void
    {
        $branch = \App\Models\SystemBranch::create(['code' => 'HT33P', 'name' => 'Chi nhánh HT33P']);
        $role = Role::create(['code' => 'pos_ht33', 'name' => 'Thu ngân', 'level' => 3, 'is_active' => true]);
        $department = \App\Models\OrganizationDepartment::create(['code' => 'D33', 'name' => 'Bộ phận 33', 'is_active' => true]);
        $position = \App\Models\Position::create(['organization_department_id' => $department->id, 'code' => 'P33', 'name' => 'Trưởng lễ tân', 'is_active' => true]);

        $this->actingAs($this->admin())->postJson("/api/organization/positions/{$position->id}/branches/sync", [
            'application_code' => 'POS', 'assignments' => [['system_branch_id' => $branch->id, 'role_id' => $role->id]],
        ])->assertOk();

        $text = \App\Models\ActivityLog::where('target_type', 'Position')->value('description');
        $this->assertStringContainsString("* Cập nhật ứng dụng POS cho vị trí công việc 'Trưởng lễ tân'", $text);
        $this->assertStringContainsString('• Thêm chi nhánh Chi nhánh HT33P: vai trò Thu ngân', $text);
    }

    /** Dòng 33/36: id của màn Hệ thống hiện theo tên, bỏ trường sắp xếp kỹ thuật. */
    public function test_row_33_system_ids_render_as_names(): void
    {
        $department = \App\Models\OrganizationDepartment::create(['code' => 'D33N', 'name' => 'Bộ phận Lễ tân', 'is_active' => true]);
        $rows = \App\Support\ActivityLogFormatter::changeRows(null, ['organization_department_id' => $department->id, 'name' => 'Nhân viên', 'sort_order' => 3]);

        $this->assertSame([
            ['field' => 'organization_department_id', 'label' => 'Bộ phận', 'before' => '', 'after' => 'Bộ phận Lễ tân'],
            ['field' => 'name', 'label' => 'Tên', 'before' => '', 'after' => 'Nhân viên'],
        ], $rows);
    }

    /** Dòng 18 / task 471: thêm ứng dụng POS cho vị trí thì nhân viên đang giữ vị trí đó vào được POS (không phải lưu lại từng nhân viên). */
    public function test_row_18_adding_application_to_position_applies_to_its_employees(): void
    {
        $department = \App\Models\OrganizationDepartment::create(['code' => 'D18', 'name' => 'Lễ tân 18', 'is_active' => true]);
        $position = \App\Models\Position::create(['organization_department_id' => $department->id, 'code' => 'P18', 'name' => 'Trưởng lễ tân', 'is_active' => true]);
        $role = Role::create(['code' => 'role_18', 'name' => 'Role 18', 'level' => 3, 'is_active' => true]);
        [$b1, $b2] = [\App\Models\SystemBranch::create(['code' => 'B181', 'name' => 'CN 1']), \App\Models\SystemBranch::create(['code' => 'B182', 'name' => 'CN 2'])];
        \App\Models\PositionBranchRole::create(['position_id' => $position->id, 'system_branch_id' => $b1->id, 'application_code' => 'PMS', 'role_id' => $role->id, 'is_active' => true]);
        $staff = User::factory()->create(['username' => 'user18', 'password' => Hash::make('password123'), 'is_active_user' => true]);
        \App\Models\UserBranchPosition::create(['user_id' => $staff->id, 'system_branch_id' => $b1->id, 'application_code' => 'PMS', 'position_id' => $position->id]);
        \App\Models\UserBranch::create(['user_id' => $staff->id, 'system_branch_id' => $b1->id, 'is_primary' => true]);
        // Cùng hàm API đăng nhập dùng để trả danh sách ứng dụng.
        $login = fn () => $staff->fresh()->assignedApplicationCodes()?->sort()->values()->all();
        $this->assertSame(['PMS'], $login());

        // POS cho CN1 (nhân viên đang ở CN1) và CN2 (nhân viên không ở CN2 -> không được thêm chi nhánh mới)
        $this->actingAs($this->admin())->postJson("/api/organization/positions/{$position->id}/branches/sync", [
            'application_code' => 'POS', 'assignments' => [['system_branch_id' => $b1->id, 'role_id' => $role->id], ['system_branch_id' => $b2->id, 'role_id' => $role->id]],
        ])->assertOk();
        $this->assertEqualsCanonicalizing(['PMS', 'POS'], $login());
        $this->assertFalse(\App\Models\UserBranchPosition::where('user_id', $staff->id)->where('system_branch_id', $b2->id)->exists());

        // Gỡ POS khỏi vị trí -> nhân viên không còn POS
        $this->actingAs($this->admin())->postJson("/api/organization/positions/{$position->id}/branches/sync", [
            'application_code' => 'POS', 'assignments' => [],
        ])->assertOk();
        $this->assertSame(['PMS'], $login());
        $this->assertFalse(\App\Models\UserBranchPosition::where('user_id', $staff->id)->where('application_code', 'POS')->exists());
    }

    /**
     * Dòng 30 / task 471 (note khách: user testuser bỏ check CN 2,3,4 vẫn thấy các chi nhánh đó):
     * Super Admin đã gán vị trí theo chi nhánh thì chỉ vào được các chi nhánh đã gán.
     */
    public function test_row_30_super_admin_by_position_only_sees_assigned_branches(): void
    {
        $department = \App\Models\OrganizationDepartment::create(['code' => 'D30', 'name' => 'Quản trị', 'is_active' => true]);
        $position = \App\Models\Position::create(['organization_department_id' => $department->id, 'code' => 'SA30', 'name' => 'Super Administrator', 'is_active' => true]);
        $superRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super', 'level' => 1, 'is_active' => true]);
        $branches = collect([1, 2, 3])->map(fn ($i) => \App\Models\SystemBranch::create(['code' => "B30{$i}", 'name' => "CN {$i}", 'is_active' => true]));
        foreach ($branches as $branch) {
            \App\Models\PositionBranchRole::create(['position_id' => $position->id, 'system_branch_id' => $branch->id, 'application_code' => 'PMS', 'role_id' => $superRole->id, 'is_active' => true]);
        }
        $admin = User::factory()->create(['username' => 'sa30', 'password' => Hash::make('password123'), 'is_active_user' => true, 'primary_branch_id' => $branches[0]->id]);
        // Chỉ còn tick chi nhánh 1
        \App\Models\UserBranchPosition::create(['user_id' => $admin->id, 'system_branch_id' => $branches[0]->id, 'application_code' => 'PMS', 'position_id' => $position->id]);
        \App\Models\UserBranch::create(['user_id' => $admin->id, 'system_branch_id' => $branches[0]->id, 'is_primary' => true]);

        $response = $this->postJson('/api/login', ['username' => 'sa30', 'password' => 'password123'])->assertOk();

        $this->assertSame([(int) $branches[0]->id], collect($response->json('branches'))->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertTrue($admin->fresh()->hasBranchAccess($branches[0]->id));
        $this->assertFalse($admin->fresh()->hasBranchAccess($branches[1]->id));
    }

    public function test_row_32_reset_password_still_requires_matching_confirmation(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/users/{$staff->id}/reset-password", ['password' => '123', 'password_confirmation' => '124'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Xác nhận mật khẩu mới không khớp.');
    }
}
