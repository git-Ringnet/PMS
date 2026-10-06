<?php

namespace App\Services;

use App\Models\HotelConfig;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\User;
use App\Support\ModuleCode;
use Illuminate\Http\Request;

class RoomStatusPermissionService
{
    public function canChange(Request $request): bool
    {
        $module = ModuleCode::normalize($request->input('current_module', ModuleCode::FRONTDESK), ModuleCode::FRONTDESK);

        if ($module === ModuleCode::RESERVATION) {
            return false;
        }

        if ($module === ModuleCode::HOUSEKEEPING) {
            return true;
        }

        $user = $request->user();
        if ($this->isSuperAdminUser($user)) {
            return true;
        }

        // 1. Công tắc chính: AllowChangeRoomStatusAtReception ('1' = Cho phép, '0' = Tắt hoàn toàn)
        $masterSwitch = HotelConfig::where('name', 'AllowChangeRoomStatusAtReception')->value('value');
        if ((string) $masterSwitch !== '1') {
            return false;
        }

        // 2. Khi công tắc chính đã BẬT, kiểm tra tiếp danh sách vai trò được phép
        $roleConfig = HotelConfig::where('name', 'RoleUserAllowChangeRoomStatusAtReception')->value('value');
        if (filled($roleConfig)) {
            $allowedRoles = preg_split('/[,;|]+|\r\n|\n/', (string) $roleConfig, -1, PREG_SPLIT_NO_EMPTY);
            $allowedRoles = array_map([$this, 'normalize'], $allowedRoles);
            $allowedRoles = array_values(array_filter($allowedRoles));

            if (!empty($allowedRoles)) {
                $userRoles = $this->getUserRoleIdentifiers($user, $this->resolveBranchId($request));

                return $this->matchesAnyRole($allowedRoles, $userRoles);
            }
        }

        return true;
    }

    public function canCancelCheckIn(Request $request): bool
    {
        if (strtolower((string) $request->input('current_module', 'frontdesk')) !== 'frontdesk') {
            return false;
        }

        $user = $request->user();
        if ($this->isSuperAdminUser($user)) {
            return true;
        }

        $roleConfig = trim((string) HotelConfig::where('name', 'RoleUserCancelCheckIn')->value('value'));
        if (filled($roleConfig)) {
            $allowedRoles = preg_split('/[,;|]+|\r\n|\n/', (string) $roleConfig, -1, PREG_SPLIT_NO_EMPTY);
            $allowedRoles = array_map([$this, 'normalize'], $allowedRoles);
            $allowedRoles = array_values(array_filter($allowedRoles));

            if (empty($allowedRoles)) {
                return true;
            }

            $userRoles = $this->getUserRoleIdentifiers($user, $this->resolveBranchId($request));

            return $this->matchesAnyRole($allowedRoles, $userRoles);
        }

        // Mặc định khi chưa cấu hình giới hạn chức danh cụ thể: cho phép bộ phận lễ tân hủy nhận phòng
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getUserRoleIdentifiers(?User $user, ?int $branchId = null): array
    {
        if (!$user) {
            return [];
        }

        $userRoles = [];

        if (strtolower($user->username ?? '') === 'admin') {
            $userRoles[] = 'admin';
            $userRoles[] = 'super_admin';
        }

        // 1. Positions & linked roles from user_branch_positions (New RBAC Schema)
        if (method_exists($user, 'userBranchPositions')) {
            try {
                $ubpQuery = $user->userBranchPositions()->with('position');
                if ($branchId) {
                    $ubpQuery->where('system_branch_id', $branchId);
                }
                $ubpList = $ubpQuery->get();

                foreach ($ubpList as $ubp) {
                    if ($ubp->position) {
                        if (!empty($ubp->position->code)) {
                            $userRoles[] = $this->normalize($ubp->position->code);
                        }
                        if (!empty($ubp->position->name)) {
                            $userRoles[] = $this->normalize($ubp->position->name);
                        }
                    }

                    $roleQuery = PositionBranchRole::where('position_id', $ubp->position_id)
                        ->where('system_branch_id', $ubp->system_branch_id)
                        ->where('is_active', true);

                    if (!empty($ubp->application_code)) {
                        $roleQuery->where('application_code', $ubp->application_code);
                    }

                    $roleId = $roleQuery->value('role_id');
                    if ($roleId) {
                        $linkedRole = Role::find($roleId);
                        if ($linkedRole) {
                            if (!empty($linkedRole->code)) {
                                $userRoles[] = $this->normalize($linkedRole->code);
                            }
                            if (!empty($linkedRole->name)) {
                                $userRoles[] = $this->normalize($linkedRole->name);
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // Ignore relation error
            }
        }

        // 2. Roles from user_roles pivot (Legacy / Fallback)
        if (method_exists($user, 'roles')) {
            try {
                $rolesQuery = $user->roles();
                if ($branchId) {
                    $rolesQuery->where(function ($nested) use ($branchId) {
                        $nested->whereNull('user_roles.system_branch_id')
                            ->orWhere('user_roles.system_branch_id', $branchId);
                    });
                }
                $rolesList = $rolesQuery->get(['roles.code', 'roles.name']);
                foreach ($rolesList as $r) {
                    if (!empty($r->code)) {
                        $userRoles[] = $this->normalize($r->code);
                    }
                    if (!empty($r->name)) {
                        $userRoles[] = $this->normalize($r->name);
                    }
                }
            } catch (\Throwable) {
                // Ignore relation error
            }
        }

        // 3. Chức danh & Bộ phận (job_title_code, job_title, department_code, department)
        if (!empty($user->job_title_code)) {
            $userRoles[] = $this->normalize($user->job_title_code);
        }
        if (!empty($user->job_title)) {
            $userRoles[] = $this->normalize($user->job_title);
        }
        if (!empty($user->department_code)) {
            $userRoles[] = $this->normalize($user->department_code);
        }
        if (!empty($user->department)) {
            $userRoles[] = $this->normalize($user->department);
        }

        return array_values(array_unique(array_filter($userRoles)));
    }

    public function matchesAnyRole(array $allowedRoles, array $userRoles): bool
    {
        $allRolesJoined = implode(' ', $userRoles);

        foreach ($allowedRoles as $allowedRole) {
            $roleLower = $this->normalize($allowedRole);
            if ($roleLower === '') {
                continue;
            }

            // Direct match
            if (in_array($roleLower, $userRoles, true)) {
                return true;
            }

            // Partial match for longer words (length >= 4 to avoid false positives like 'fo' in 'fom')
            if (mb_strlen($roleLower) >= 4) {
                foreach ($userRoles as $uRole) {
                    if (mb_strlen($uRole) >= 4 && (str_contains($uRole, $roleLower) || str_contains($roleLower, $uRole))) {
                        return true;
                    }
                }
            }

            // Aliases / conventions
            if ($roleLower === 'admin' && (
                in_array('administrator', $userRoles, true) ||
                in_array('super_admin', $userRoles, true) ||
                in_array('branch_admin', $userRoles, true) ||
                in_array('mgmt', $userRoles, true) ||
                str_contains($allRolesJoined, 'quản trị') ||
                str_contains($allRolesJoined, 'tổng giám đốc') ||
                str_contains($allRolesJoined, 'quản lý')
            )) {
                return true;
            }

            // FOM / Trưởng Lễ Tân
            if (in_array($roleLower, ['fom', 'fo_manager'], true) || str_contains($roleLower, 'trưởng lễ tân')) {
                if (
                    in_array('fom', $userRoles, true) ||
                    in_array('fo_manager', $userRoles, true) ||
                    str_contains($allRolesJoined, 'trưởng bộ phận lễ tân') ||
                    str_contains($allRolesJoined, 'trưởng lễ tân')
                ) {
                    return true;
                }
                continue;
            }

            // FO / Nhân viên lễ tân / Tiếp tân
            if (in_array($roleLower, ['fo', 'fo_staff'], true) || str_contains($roleLower, 'nhân viên lễ tân') || str_contains($roleLower, 'tiếp tân')) {
                if (
                    in_array('fo', $userRoles, true) ||
                    in_array('fo_staff', $userRoles, true) ||
                    str_contains($allRolesJoined, 'nhân viên lễ tân') ||
                    str_contains($allRolesJoined, 'tiếp tân')
                ) {
                    return true;
                }
                continue;
            }

            // Bộ phận Lễ tân chung (cả FOM lẫn FO)
            if (in_array($roleLower, ['lễ tân', 'reception', 'frontdesk', 'front office'], true)) {
                if (
                    in_array('fo', $userRoles, true) ||
                    in_array('fom', $userRoles, true) ||
                    in_array('fo_staff', $userRoles, true) ||
                    in_array('fo_manager', $userRoles, true) ||
                    str_contains($allRolesJoined, 'lễ tân') ||
                    str_contains($allRolesJoined, 'reception')
                ) {
                    return true;
                }
            }

            // HKM / Trưởng buồng
            if (in_array($roleLower, ['hkm', 'hk_manager'], true) || str_contains($roleLower, 'trưởng buồng')) {
                if (
                    in_array('hkm', $userRoles, true) ||
                    in_array('hk_manager', $userRoles, true) ||
                    str_contains($allRolesJoined, 'trưởng bộ phận buồng') ||
                    str_contains($allRolesJoined, 'trưởng buồng')
                ) {
                    return true;
                }
                continue;
            }

            // HK / Nhân viên buồng
            if (in_array($roleLower, ['hk', 'hk_staff'], true) || str_contains($roleLower, 'nhân viên buồng')) {
                if (
                    in_array('hk', $userRoles, true) ||
                    in_array('hk_staff', $userRoles, true) ||
                    str_contains($allRolesJoined, 'nhân viên buồng')
                ) {
                    return true;
                }
                continue;
            }

            // Bộ phận Buồng phòng chung
            if (in_array($roleLower, ['buồng', 'buồng phòng', 'housekeeping'], true)) {
                if (
                    in_array('hk', $userRoles, true) ||
                    in_array('hkm', $userRoles, true) ||
                    in_array('hk_staff', $userRoles, true) ||
                    in_array('hk_manager', $userRoles, true) ||
                    str_contains($allRolesJoined, 'buồng') ||
                    str_contains($allRolesJoined, 'housekeeping')
                ) {
                    return true;
                }
            }

            if ($roleLower === 'sales' && (
                in_array('sales', $userRoles, true) ||
                str_contains($allRolesJoined, 'kinh doanh') ||
                str_contains($allRolesJoined, 'sales')
            )) {
                return true;
            }
        }

        return false;
    }

    private function isSuperAdminUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (!empty($user->is_super_admin)) {
            return true;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return strtolower($user->username ?? '') === 'admin';
    }

    private function resolveBranchId(Request $request): ?int
    {
        $branchId = $request->header('X-Branch-Id') ?? $request->input('branch_id');
        return is_numeric($branchId) ? (int) $branchId : null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
