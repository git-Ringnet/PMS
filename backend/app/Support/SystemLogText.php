<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\SystemBranch;
use Illuminate\Support\Collection;

/**
 * Checklist TC_HT_06 dòng 33: nội dung Lịch sử thao tác của các màn Hệ thống ghi rõ ràng bằng tên
 * (vai trò, chi nhánh, màn hình, vị trí) thay vì id kỹ thuật; lưu ma trận quyền / copy quyền ghi MỘT dòng
 * tổng hợp thay vì chỉ thấy quyền cuối cùng được tạo.
 */
class SystemLogText
{
    public const SCREEN_ROLES = 'Vai trò & Phân quyền';
    public const SCREEN_ORG = 'Cơ cấu Tổ chức';
    public const SCREEN_EMPLOYEE = 'Nhân viên';

    private const ACTIONS = ['view' => 'Xem', 'add' => 'Thêm', 'create' => 'Thêm', 'edit' => 'Sửa', 'update' => 'Sửa', 'delete' => 'Xóa'];

    /** Tối đa số quyền liệt kê trong mỗi nhóm "Cấp thêm" / "Bỏ quyền", phần còn lại ghi "... và N quyền khác". */
    private const LIST_LIMIT = 40;

    public static function branchName(?int $branchId): string
    {
        return $branchId ? (SystemBranch::find($branchId)?->name ?? "#{$branchId}") : '(trống)';
    }

    public static function roleName(?int $roleId): string
    {
        return $roleId ? (Role::find($roleId)?->name ?? "#{$roleId}") : '(trống)';
    }

    /** "Trả phòng - Xem" */
    public static function permissionLabel(Permission $permission): string
    {
        $screen = $permission->screen_name ?: $permission->name ?: $permission->code;
        $action = self::ACTIONS[strtolower((string) ($permission->action ?: 'view'))] ?? $permission->action;

        return "{$screen} - {$action}";
    }

    /** @param  iterable<int>  $ids */
    private static function permissionList(iterable $ids): string
    {
        $labels = Permission::query()->whereIn('id', collect($ids)->all())->orderBy('module')->orderBy('sort_order')->get()
            ->map(fn (Permission $permission) => self::permissionLabel($permission))->values();
        $shown = $labels->take(self::LIST_LIMIT)->implode(', ');
        $more = $labels->count() - self::LIST_LIMIT;

        return $more > 0 ? "{$shown} ... và {$more} quyền khác" : $shown;
    }

    /** Lưu ma trận quyền của vai trò tại chi nhánh. */
    public static function rolePermissionSync(Role $role, int $branchId, string $applicationCode, Collection $beforeIds, Collection $afterIds): string
    {
        $added = $afterIds->diff($beforeIds)->values();
        $removed = $beforeIds->diff($afterIds)->values();
        $lines = ["* Lưu phân quyền vai trò '{$role->name}' - Chi nhánh: " . self::branchName($branchId) . " - Ứng dụng: {$applicationCode}"];
        if ($added->isNotEmpty()) {
            $lines[] = "• Cấp thêm ({$added->count()}): " . self::permissionList($added);
        }
        if ($removed->isNotEmpty()) {
            $lines[] = "• Bỏ quyền ({$removed->count()}): " . self::permissionList($removed);
        }
        if ($added->isEmpty() && $removed->isEmpty()) {
            $lines[] = '• Không thay đổi quyền';
        }
        $lines[] = "• Tổng số quyền sau khi lưu: {$afterIds->count()}";

        return implode("\n", $lines);
    }

    public static function roleCopy(Role $source, Role $copy, int $branchId, string $applicationCode, int $count): string
    {
        return "* Copy phân quyền từ vai trò '{$source->name}' sang vai trò mới '{$copy->name}' (Mã: {$copy->code})\n"
            . '• Chi nhánh: ' . self::branchName($branchId) . "\n"
            . "• Ứng dụng: {$applicationCode}\n"
            . "• Số quyền đã sao chép: {$count}";
    }

    /**
     * Gán ứng dụng cho vị trí công việc: mỗi chi nhánh dùng vai trò nào.
     *
     * @param  array<int,int>  $before  [branch_id => role_id]
     * @param  array<int,int>  $after   [branch_id => role_id]
     */
    public static function positionApplications(Position $position, string $applicationCode, array $before, array $after): string
    {
        $lines = ["* Cập nhật ứng dụng {$applicationCode} cho vị trí công việc '{$position->name}'"];
        foreach ($after as $branchId => $roleId) {
            $old = $before[$branchId] ?? null;
            if ($old === null) {
                $lines[] = '• Thêm chi nhánh ' . self::branchName($branchId) . ': vai trò ' . self::roleName($roleId);
            } elseif ((int) $old !== (int) $roleId) {
                $lines[] = '• Chi nhánh ' . self::branchName($branchId) . ': vai trò ' . self::roleName($old) . ' ➜ ' . self::roleName($roleId);
            }
        }
        foreach ($before as $branchId => $roleId) {
            if (!array_key_exists($branchId, $after)) {
                $lines[] = '• Bỏ chi nhánh ' . self::branchName($branchId) . ' (vai trò ' . self::roleName($roleId) . ')';
            }
        }
        if (count($lines) === 1) {
            $lines[] = $after === [] ? '• Gỡ ứng dụng khỏi vị trí' : '• Không thay đổi';
        }

        return implode("\n", $lines);
    }

    /**
     * Phân quyền chi nhánh / vị trí cho nhân viên.
     *
     * @param  array<string,int>  $before  ["branchId|APP" => position_id]
     * @param  array<string,int>  $after
     */
    public static function userAssignments(string $userName, array $before, array $after, ?int $primaryBranchId): string
    {
        $describe = function (string $key, int $positionId): string {
            [$branchId, $app] = explode('|', $key);
            $position = Position::find($positionId)?->name ?? "#{$positionId}";

            return self::branchName((int) $branchId) . " - {$app}: {$position}";
        };
        $lines = ["* Cập nhật chi nhánh & vị trí công việc của nhân viên '{$userName}'"];
        foreach ($after as $key => $positionId) {
            if (!array_key_exists($key, $before)) {
                $lines[] = '• Thêm: ' . $describe($key, $positionId);
            } elseif ((int) $before[$key] !== (int) $positionId) {
                $lines[] = '• Đổi: ' . $describe($key, (int) $before[$key]) . ' ➜ ' . (Position::find($positionId)?->name ?? "#{$positionId}");
            }
        }
        foreach ($before as $key => $positionId) {
            if (!array_key_exists($key, $after)) {
                $lines[] = '• Bỏ: ' . $describe($key, $positionId);
            }
        }
        if ($primaryBranchId) {
            $lines[] = '• Chi nhánh chính: ' . self::branchName($primaryBranchId);
        }

        return implode("\n", $lines);
    }
}
