<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockNightAuditRequests;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dòng 406: phân quyền theo màn hình, API cấu hình và màn hình Sao lưu / Khôi phục database.
 */
class Row406ScreenPermissionTest extends TestCase
{
    use RefreshDatabase;

    private SystemBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(BlockNightAuditRequests::class);
        config(['database_domains.branch_connections' => ['HKT1' => 'sqlite']]);

        $this->branch = SystemBranch::firstOrCreate(
            ['code' => 'HKT1'],
            ['name' => 'Chi nhánh 1', 'is_active' => true]
        );
    }

    private function userWithRole(string $roleCode, array $permissionCodes = []): User
    {
        $role = Role::firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleCode, 'level' => 3, 'is_active' => true]
        );
        $role->permissions()->sync(collect($permissionCodes)->map(
            fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO'])->id
        )->all());

        $user = User::create([
            'name' => $roleCode,
            'username' => $roleCode . '_' . uniqid(),
            'email' => $roleCode . uniqid() . '@pms.test',
            'password' => bcrypt('Secret@123'),
            'primary_branch_id' => $this->branch->id,
            'is_active_user' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'system_branch_id' => null]);

        return $user;
    }

    public function test_row_406_database_backup_is_admin_only_even_with_system_setting(): void
    {
        Sanctum::actingAs($this->userWithRole('fo_staff', ['system.setting', 'fo.booking.view']));

        $this->getJson('/api/system/database/info')->assertStatus(403);
        $this->postJson('/api/system/database/export', ['password' => 'Secret@123'])->assertStatus(403);
        $this->post('/api/system/database/import', [
            'password' => 'Secret@123',
            'file' => UploadedFile::fake()->createWithContent('backup.sql', 'SELECT 1;'),
        ], ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_row_406_database_export_and_import_require_correct_password(): void
    {
        Sanctum::actingAs($this->userWithRole('branch_admin'));

        $this->postJson('/api/system/database/export', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/system/database/export', ['password' => 'sai-mat-khau'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Mật khẩu xác nhận không đúng');

        $this->post('/api/system/database/import', [
            'password' => 'sai-mat-khau',
            'file' => UploadedFile::fake()->createWithContent('backup.sql', 'SELECT 1;'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Mật khẩu xác nhận không đúng');
    }

    public function test_row_406_correct_password_passes_confirmation(): void
    {
        Sanctum::actingAs($this->userWithRole('branch_admin'));

        // Đúng mật khẩu thì qua bước xác nhận và trả file sao lưu
        $this->postJson('/api/system/database/export', ['password' => 'Secret@123'])->assertOk();
    }

    public function test_row_406_database_export_is_no_longer_reachable_with_get(): void
    {
        // Mật khẩu không được gửi qua query string nên xuất DB chỉ còn POST
        $route = app('router')->getRoutes()->match(Request::create('/api/system/database/export', 'GET'));

        $this->assertStringNotContainsString('DatabaseBackupController', $route->getActionName());
    }

    public function test_row_406_config_catalog_is_readable_but_not_writable_without_screen_permission(): void
    {
        Sanctum::actingAs($this->userWithRole('fo_staff', ['fo.booking.view', 'fo.booking.create']));

        // Đặt phòng vẫn đọc được danh mục loại phòng / phương thức thanh toán
        $this->getJson('/api/room-classes')->assertOk();
        $this->getJson('/api/payment-methods')->assertOk();

        $this->postJson('/api/room-classes', ['name' => 'VIP', 'code' => 'VIP'])->assertStatus(403);
        $this->postJson('/api/payment-methods', ['name' => 'Tiền mặt'])->assertStatus(403);
        $this->putJson('/api/hotel-settings', ['name' => 'Hotel'])->assertStatus(403);
        $this->postJson('/api/hotel-configs', ['name' => 'CheckAuthorization', 'value' => '0'])->assertStatus(403);
        $this->postJson('/api/templates', ['name' => 'Mẫu'])->assertStatus(403);
        $this->putJson('/api/hk-config/symbols', [])->assertStatus(403);
        $this->postJson('/api/system-date/roll')->assertStatus(403);
    }

    public function test_row_406_screen_permission_or_legacy_system_setting_unlocks_config_write(): void
    {
        Sanctum::actingAs($this->userWithRole('room_config', ['config.room_classes.create']));
        $this->assertNotSame(403, $this->postJson('/api/room-classes', [])->status());
        // Có quyền Định nghĩa phòng nhưng không có quyền Thiết lập giá
        $this->postJson('/api/standard-rates', [])->assertStatus(403);

        Sanctum::actingAs($this->userWithRole('legacy_setting', ['system.setting']));
        $this->assertNotSame(403, $this->postJson('/api/standard-rates', [])->status());
    }

    public function test_row_406_sensitive_reads_require_permission(): void
    {
        Sanctum::actingAs($this->userWithRole('hk_staff', ['hk.view']));

        $this->getJson('/api/activity-logs')->assertStatus(403);
        $this->getJson('/api/sales-invoices')->assertStatus(403);

        Sanctum::actingAs($this->userWithRole('fo_manager', ['mgmt.activity_log']));
        $this->assertNotSame(403, $this->getJson('/api/activity-logs')->status());
    }

    public function test_row_406_migration_adds_config_screen_permissions_to_catalog(): void
    {
        foreach (['config.system', 'config.housekeeping', 'config.template'] as $screen) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $this->assertDatabaseHas('permissions', [
                    'code' => "{$screen}.{$action}",
                    'screen_code' => $screen,
                    'module' => 'CONFIG',
                ]);
            }
        }
    }
}
