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
     * Check if user has role permission to unlock room.
     * Returns error string if unauthorized, or null if permitted.
     */
    public function checkUnlockRolePermission(?User $user, $lock = null): ?string
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

        $allowedRoles = preg_split('/[,;|]+/', strtolower((string) $allowedRolesStr), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($allowedRoles)) {
            return null;
        }

        // Thu thập danh sách Roles (tên Rule/vai trò, positions, job_title, department) của user
        $userRoles = [];

        // Admin username check
        if (strtolower($user->username ?? '') === 'admin') {
            $userRoles[] = 'admin';
            $userRoles[] = 'super_admin';
        }

        // 1. Positions & linked roles from user_branch_positions (New RBAC Schema)
        if (method_exists($user, 'userBranchPositions')) {
            try {
                $ubpList = $user->userBranchPositions()->with('position')->get();
                foreach ($ubpList as $ubp) {
                    if ($ubp->position) {
                        if (!empty($ubp->position->code)) {
                            $userRoles[] = strtolower(trim((string)$ubp->position->code));
                        }
                        if (!empty($ubp->position->name)) {
                            $userRoles[] = strtolower(trim((string)$ubp->position->name));
                        }
                    }

                    // Role linked via PositionBranchRole
                    $roleId = PositionBranchRole::where('position_id', $ubp->position_id)
                        ->where('system_branch_id', $ubp->system_branch_id)
                        ->where('application_code', $ubp->application_code)
                        ->where('is_active', true)
                        ->value('role_id');

                    if ($roleId) {
                        $linkedRole = Role::find($roleId);
                        if ($linkedRole) {
                            if (!empty($linkedRole->code)) {
                                $userRoles[] = strtolower(trim((string)$linkedRole->code));
                            }
                            if (!empty($linkedRole->name)) {
                                $userRoles[] = strtolower(trim((string)$linkedRole->name));
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore relation error
            }
        }

        // 2. Roles from user_roles pivot (Legacy / Fallback)
        if (method_exists($user, 'roles')) {
            try {
                $rolesList = $user->roles;
                if ($rolesList) {
                    foreach ($rolesList as $r) {
                        if (!empty($r->code)) $userRoles[] = strtolower(trim((string)$r->code));
                        if (!empty($r->name)) $userRoles[] = strtolower(trim((string)$r->name));
                    }
                }
            } catch (\Throwable $e) {
                // Ignore relation error
            }
        }

        // 3. Chức danh & Bộ phận (job_title_code, job_title, department_code, department)
        if (!empty($user->job_title_code)) $userRoles[] = strtolower(trim((string)$user->job_title_code));
        if (!empty($user->job_title)) $userRoles[] = strtolower(trim((string)$user->job_title));
        if (!empty($user->department_code)) $userRoles[] = strtolower(trim((string)$user->department_code));
        if (!empty($user->department)) $userRoles[] = strtolower(trim((string)$user->department));

        $userRoles = array_unique(array_filter($userRoles));
        $allRolesJoined = implode(' ', $userRoles);

        foreach ($allowedRoles as $allowedRole) {
            $roleLower = trim(strtolower($allowedRole));

            // So khớp trực tiếp
            if (in_array($roleLower, $userRoles, true)) {
                return null;
            }

            // So khớp từng phần
            foreach ($userRoles as $uRole) {
                if ($uRole === $roleLower || str_contains($uRole, $roleLower) || str_contains($roleLower, $uRole)) {
                    return null;
                }
            }

            // Xử lý các nhóm vai trò nghiệp vụ (Aliases / Quy ước PMS):
            // Admin / Quản trị
            if ($roleLower === 'admin' && (
                in_array('administrator', $userRoles, true) ||
                in_array('super_admin', $userRoles, true) ||
                in_array('branch_admin', $userRoles, true) ||
                in_array('mgmt', $userRoles, true) ||
                str_contains($allRolesJoined, 'quản trị') ||
                str_contains($allRolesJoined, 'tổng giám đốc') ||
                str_contains($allRolesJoined, 'quản lý')
            )) {
                return null;
            }

            // FO / FOM / Lễ tân
            if (in_array($roleLower, ['fo', 'fom'], true) && (
                in_array('fo', $userRoles, true) ||
                in_array('fom', $userRoles, true) ||
                in_array('fo_staff', $userRoles, true) ||
                in_array('fo_manager', $userRoles, true) ||
                str_contains($allRolesJoined, 'lễ tân') ||
                str_contains($allRolesJoined, 'reception')
            )) {
                return null;
            }

            // HK / HKM / Buồng phòng
            if (in_array($roleLower, ['hk', 'hkm'], true) && (
                in_array('hk', $userRoles, true) ||
                in_array('hkm', $userRoles, true) ||
                in_array('hk_staff', $userRoles, true) ||
                in_array('hk_manager', $userRoles, true) ||
                str_contains($allRolesJoined, 'buồng') ||
                str_contains($allRolesJoined, 'housekeeping')
            )) {
                return null;
            }

            // Sales / Kinh doanh
            if ($roleLower === 'sales' && (
                in_array('sales', $userRoles, true) ||
                str_contains($allRolesJoined, 'kinh doanh') ||
                str_contains($allRolesJoined, 'sales')
            )) {
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
}
