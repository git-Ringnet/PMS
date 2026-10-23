<?php

namespace App\Services\Reports;

use App\Models\BranchRolePermission;
use App\Models\Permission;
use App\Models\ReportDefinition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dòng 328: mỗi báo cáo trong danh mục có một quyền xem riêng (report.<mã>.view, phân hệ REPORT),
 * hiển thị ở màn Phân quyền theo rule và quyết định người dùng có được xem báo cáo hay không.
 */
class ReportPermissionService
{
    public const MODULE = 'REPORT';

    /** Mã quyền chỉ đổi theo mã báo cáo, nên báo cáo cùng mã ở các chi nhánh dùng chung một quyền. */
    public static function permissionCode(ReportDefinition|string $report): string
    {
        $code = $report instanceof ReportDefinition ? $report->code : $report;

        return 'report.'.strtolower($code).'.view';
    }

    /**
     * Tạo / cập nhật quyền xem cho báo cáo. Quyền vừa tạo được cấp cho các vai trò đang có
     * để báo cáo mới vẫn hiện như trước, quản trị bỏ chọn ở màn phân quyền nếu muốn ẩn.
     */
    public function sync(ReportDefinition $report): Permission
    {
        $code = self::permissionCode($report);
        $permission = Permission::updateOrCreate(['code' => $code], [
            'name' => 'Xem báo cáo '.$report->name,
            'module' => self::MODULE,
            'application_code' => $this->applicationCode(),
            'screen_code' => 'report.'.strtolower($report->code),
            'screen_name' => $report->name,
            'path' => '/reports?report='.$report->code,
            'action' => 'view',
            'screen_type' => 'report',
            'sort_order' => (int) $report->sort_order,
        ]);

        if ($permission->wasRecentlyCreated) {
            $this->grantToExistingRoles($permission);
        }

        return $permission;
    }

    /** Báo cáo nào chưa có quyền xem (vd. thêm bằng migration) thì tạo bổ sung. */
    public function ensurePermissions(Collection $reports): void
    {
        if ($reports->isEmpty()) {
            return;
        }

        $existing = Permission::whereIn('code', $reports->map(fn ($report) => self::permissionCode($report)))->pluck('code')->all();
        foreach ($reports as $report) {
            if (! in_array(self::permissionCode($report), $existing, true)) {
                $this->sync($report);
            }
        }
    }

    /** Lọc danh sách báo cáo người dùng được xem tại chi nhánh. */
    public function visibleReports(?User $user, Collection $reports, ?int $branchId): Collection
    {
        if (! $user) {
            return collect();
        }
        if ($user->isSuperAdmin()) {
            return $reports->values();
        }

        $granted = $user->allPermissions($branchId, $this->applicationCode())->flip();

        return $reports->filter(fn ($report) => $granted->has(self::permissionCode($report)))->values();
    }

    public function canView(?User $user, ReportDefinition $report, ?int $branchId): bool
    {
        return $this->visibleReports($user, collect([$report]), $branchId)->isNotEmpty();
    }

    /** Các vai trò và trạng thái được xem báo cáo tại một chi nhánh (màn Quyền xem báo cáo). */
    public function roleAccess(ReportDefinition $report, int $branchId): array
    {
        $permission = $this->sync($report);
        $grantedRoleIds = BranchRolePermission::where('system_branch_id', $branchId)
            ->where('permission_id', $permission->id)
            ->pluck('role_id')
            ->all();

        return Role::query()
            ->where('code', '!=', 'super_admin')
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'is_active' => (bool) $role->is_active,
                'granted' => in_array($role->id, $grantedRoleIds),
            ])
            ->all();
    }

    public function syncRoleAccess(ReportDefinition $report, int $branchId, array $roleIds): void
    {
        $permission = $this->sync($report);
        $connection = config('database_domains.system_connection', 'mysql_system');

        DB::connection($connection)->transaction(function () use ($permission, $branchId, $roleIds) {
            BranchRolePermission::where('system_branch_id', $branchId)
                ->where('permission_id', $permission->id)
                ->whereNotIn('role_id', $roleIds)
                ->delete();

            $existing = BranchRolePermission::where('system_branch_id', $branchId)
                ->where('permission_id', $permission->id)
                ->pluck('role_id')
                ->all();
            foreach (array_diff($roleIds, $existing) as $roleId) {
                BranchRolePermission::create([
                    'system_branch_id' => $branchId,
                    'role_id' => $roleId,
                    'permission_id' => $permission->id,
                ]);
            }
        });
    }

    private function grantToExistingRoles(Permission $permission): void
    {
        $pairs = BranchRolePermission::query()
            ->select('system_branch_id', 'role_id')
            ->distinct()
            ->get();
        foreach ($pairs as $pair) {
            BranchRolePermission::firstOrCreate([
                'system_branch_id' => $pair->system_branch_id,
                'role_id' => $pair->role_id,
                'permission_id' => $permission->id,
            ]);
        }

        // Người dùng chưa chuyển sang phân quyền theo chi nhánh vẫn dùng quyền theo vai trò cũ
        $connection = config('database_domains.system_connection', 'mysql_system');
        $legacyRoleIds = DB::connection($connection)->table('role_permissions')->distinct()->pluck('role_id');
        foreach ($legacyRoleIds as $roleId) {
            DB::connection($connection)->table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permission->id,
            ]);
        }
    }

    private function applicationCode(): string
    {
        return strtoupper(config('database_domains.default_application_code', 'PMS'));
    }
}
