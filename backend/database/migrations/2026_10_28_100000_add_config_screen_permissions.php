<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng 406: bổ sung quyền theo màn hình còn thiếu trong Cấu hình hệ thống
 * (Định nghĩa hệ thống, Định nghĩa buồng phòng, Mẫu in & Báo cáo) để admin tick được trong màn hình phân quyền.
 * Đồng bộ với RbacMatrixSeeder.
 */
return new class extends Migration
{
    private const SCREENS = [
        'config.system' => ['Định nghĩa hệ thống (Thanh toán, Tiền tệ, Trạng thái...)', [
            'view' => ['config.system.view', 'Xem định nghĩa hệ thống'],
            'add' => ['config.system.create', 'Thêm danh mục hệ thống'],
            'edit' => ['config.system.edit', 'Sửa danh mục hệ thống'],
            'delete' => ['config.system.delete', 'Xóa danh mục hệ thống'],
        ]],
        'config.housekeeping' => ['Định nghĩa buồng phòng', [
            'view' => ['config.housekeeping.view', 'Xem định nghĩa buồng phòng'],
            'add' => ['config.housekeeping.create', 'Thêm định nghĩa buồng phòng'],
            'edit' => ['config.housekeeping.edit', 'Sửa định nghĩa buồng phòng'],
            'delete' => ['config.housekeeping.delete', 'Xóa định nghĩa buồng phòng'],
        ]],
        'config.template' => ['Mẫu in & Báo cáo', [
            'view' => ['config.template.view', 'Xem mẫu in & báo cáo'],
            'add' => ['config.template.create', 'Tạo mẫu in & báo cáo'],
            'edit' => ['config.template.edit', 'Sửa mẫu in & báo cáo'],
            'delete' => ['config.template.delete', 'Xóa mẫu in & báo cáo'],
        ]],
    ];

    /** Quyền mặc định theo vai trò, giống các màn hình cấu hình khác trong RbacMatrixSeeder. */
    private const ROLE_GRANTS = [
        'branch_admin' => ['view', 'add', 'edit', 'delete'],
        'mgmt' => ['view'],
    ];

    public function up(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $schema = Schema::connection($system);
        if (! $schema->hasTable('permissions') || ! $schema->hasColumn('permissions', 'screen_code')) {
            return;
        }

        $db = DB::connection($system);
        $sortOrder = (int) $db->table('permissions')->max('sort_order');
        $now = now();
        $idsByAction = [];

        foreach (self::SCREENS as $screenCode => [$screenName, $actions]) {
            foreach ($actions as $action => [$code, $name]) {
                $db->table('permissions')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'module' => 'CONFIG',
                        'application_code' => 'PMS',
                        'screen_code' => $screenCode,
                        'screen_name' => $screenName,
                        'action' => $action,
                        'screen_type' => 'screen',
                        'sort_order' => ++$sortOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $idsByAction[$action][] = $db->table('permissions')->where('code', $code)->value('id');
            }
        }

        if (! $schema->hasTable('roles')) {
            return;
        }

        $branchIds = $schema->hasTable('system_branches') ? $db->table('system_branches')->pluck('id') : collect();

        foreach (self::ROLE_GRANTS as $roleCode => $actions) {
            $roleId = $db->table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = collect($actions)->flatMap(fn ($action) => $idsByAction[$action] ?? []);
            foreach ($permissionIds as $permissionId) {
                if ($schema->hasTable('role_permissions')) {
                    $db->table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
                if ($schema->hasTable('branch_role_permissions')) {
                    foreach ($branchIds as $branchId) {
                        $db->table('branch_role_permissions')->insertOrIgnore([
                            'system_branch_id' => $branchId,
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        if (Schema::connection($system)->hasTable('permissions')) {
            DB::connection($system)->table('permissions')
                ->whereIn('screen_code', array_keys(self::SCREENS))
                ->delete();
        }
    }
};
