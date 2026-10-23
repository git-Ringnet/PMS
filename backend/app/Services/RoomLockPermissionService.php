<?php

namespace App\Services;

use App\Models\HotelConfig;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\RoomLock;
use App\Models\User;

class RoomLockPermissionService
{
    /**
     * Mã Rule ngắn trên màn thông số (Admin, FO, FOM...) ứng với mã vai trò trong Phân quyền theo rule.
     * Ngoài các mã này, giá trị thông số có thể ghi thẳng mã / tên vai trò (vd fo_manager, Trưởng Lễ Tân).
     */
    private const ROLE_ALIASES = [
        'admin' => ['super_admin', 'branch_admin'],
        'fo' => ['fo_staff'],
        'fom' => ['fo_manager'],
        'hk' => ['hk_staff'],
        'hkm' => ['hk_manager'],
        'fb' => ['fb_staff'],
        'fbm' => ['fb_manager'],
        'mgmt' => ['mgmt'],
        'sales' => ['sales'],
        'acc' => ['acc'],
    ];

    /**
     * Check if user has role permission to unlock room.
     * Returns error string if unauthorized, or null if permitted.
     */
    public function checkUnlockRolePermission(?User $user, $lock = null, ?int $branchId = null): ?string
    {
        if (!$user) {
            return 'Vui lòng đăng nhập để thực hiện thao tác mở khóa phòng.';
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return null;
        }

        // 1. Ưu tiên đọc cấu hình RoleUserUnlockRoomOOO/OOS
        $allowedRolesStr = HotelConfig::where('name', 'RoleUserUnlockRoomOOO/OOS')->value('value');

        // 2. Fallback về cấu hình riêng lẻ nếu chưa cấu hình chung
        if ($allowedRolesStr === null || trim((string)$allowedRolesStr) === '') {
            $lockType = 'OOO';
            if ($lock instanceof RoomLock) {
                $lockType = $lock->lock_type ?? 'OOO';
            } elseif (is_array($lock)) {
                $lockType = $lock['lock_type'] ?? 'OOO';
            }
            $isOoo = strtoupper((string)$lockType) === 'OOO';
            $configName = $isOoo ? 'OOORoleUserUnlock' : 'OOSRoleUserUnlock';
            $allowedRolesStr = HotelConfig::where('name', $configName)->value('value');
        }

        if (empty($allowedRolesStr) || trim((string)$allowedRolesStr) === '') {
            return null; // Không cấu hình => không giới hạn
        }

        $allowedRoles = array_values(array_filter(array_map(
            fn ($role) => mb_strtolower(trim($role)),
            preg_split('/[,;|]+/', (string) $allowedRolesStr, -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
        if (empty($allowedRoles)) {
            return null;
        }

        // Dòng 153: giá trị thông số là tên Rule (vai trò), so khớp chính xác với vai trò của user tại chi nhánh đang dùng.
        // Không so theo tên user, chức danh, bộ phận và không so khớp một phần (trước đây "FO" khớp cả "fo_manager").
        $userRoles = $this->userRoleIdentifiers($user, $branchId ?? request()?->attributes->get('_branch_id'));

        foreach ($allowedRoles as $allowedRole) {
            $accepted = [$allowedRole, ...(self::ROLE_ALIASES[$allowedRole] ?? [])];
            if (array_intersect($accepted, $userRoles)) {
                return null;
            }
        }

        return "Tài khoản của bạn không thuộc vai trò (Role) được phép mở khóa phòng (Vai trò yêu cầu: {$allowedRolesStr}).";
    }

    /**
     * Check if user department has permissions to unlock.
     * Returns error string if unauthorized, or null if permitted.
     */
    public function checkUnlockDepartmentPermission(?User $user, $lock = null): ?string
    {
        if (!$user || !$lock) {
            return null;
        }

        $checkOoo = HotelConfig::where('name', 'OOOCheckDepartment')->value('value') ?? '0';
        $checkOos = HotelConfig::where('name', 'OOSCheckDepartment')->value('value') ?? '0';

        $lockType = 'OOO';
        $lockUsername = null;
        if ($lock instanceof RoomLock) {
            $lockType = $lock->lock_type ?? 'OOO';
            $lockUsername = $lock->username;
        } elseif (is_array($lock)) {
            $lockType = $lock['lock_type'] ?? 'OOO';
            $lockUsername = $lock['username'] ?? null;
        }

        $isOoo = strtoupper((string)$lockType) === 'OOO';
        $isOos = strtoupper((string)$lockType) === 'OOS';

        if (($isOoo && $checkOoo === '1') || ($isOos && $checkOos === '1')) {
            if ($lockUsername) {
                $locker = User::where('username', $lockUsername)
                    ->orWhere('name', $lockUsername)
                    ->first();

                if ($locker && !empty($locker->department) && $user->department !== $locker->department) {
                    return "Bạn không thuộc bộ phận đã thực hiện khóa phòng này (Bộ phận: {$locker->department}), không thể mở khóa.";
                }
            }
        }

        return null;
    }

    /** Mã và tên các vai trò (Rule) của user tại chi nhánh, chữ thường. */
    private function userRoleIdentifiers(User $user, ?int $branchId): array
    {
        $applicationCode = strtoupper((string) config('database_domains.default_application_code', 'PMS'));
        $roles = collect();

        if ($user->userBranchPositions()->where('application_code', $applicationCode)->exists()) {
            $assignments = $user->userBranchPositions()
                ->where('application_code', $applicationCode)
                ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
                ->get();
            foreach ($assignments as $assignment) {
                $role = PositionBranchRole::query()
                    ->where('position_id', $assignment->position_id)
                    ->where('system_branch_id', $assignment->system_branch_id)
                    ->where('application_code', $assignment->application_code)
                    ->where('is_active', true)
                    ->with('role')
                    ->first()?->role;
                if ($role) {
                    $roles->push($role);
                }
            }
        } else {
            $roles = $user->roles()
                ->when($branchId, fn ($query) => $query->where(function ($nested) use ($branchId) {
                    $nested->whereNull('user_roles.system_branch_id')
                        ->orWhere('user_roles.system_branch_id', $branchId);
                }))
                ->get(['roles.code', 'roles.name']);
        }

        return $roles->flatMap(fn ($role) => [$role->code, $role->name])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
