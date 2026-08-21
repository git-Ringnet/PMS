<?php

namespace App\Services;

use App\Models\HotelConfig;
use Illuminate\Http\Request;
use App\Support\ModuleCode;

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

        $roleConfig = HotelConfig::where('name', 'RoleUserAllowChangeRoomStatusAtReception')->value('value');
        if (filled($roleConfig)) {
            $allowedRoles = preg_split('/[,;|]+/', strtolower((string) $roleConfig), -1, PREG_SPLIT_NO_EMPTY);
            $user = $request->user();
            $userRoles = array_filter([
                strtolower((string) ($user?->job_title_code ?? '')),
                strtolower((string) ($user?->job_title ?? '')),
            ]);

            return count(array_intersect($allowedRoles, $userRoles)) > 0;
        }

        return HotelConfig::where('name', 'AllowChangeRoomStatusAtReception')->value('value') === '1';
    }

    public function canCancelCheckIn(Request $request): bool
    {
        if (strtolower((string) $request->input('current_module', 'frontdesk')) !== 'frontdesk') {
            return false;
        }

        $roleConfig = trim((string) HotelConfig::where('name', 'RoleUserCancelCheckIn')->value('value'));
        if ($roleConfig === '') {
            return false;
        }

        $allowedRoles = preg_split('/[,;|]+/', strtolower($roleConfig), -1, PREG_SPLIT_NO_EMPTY);
        $user = $request->user();
        $userRoles = array_filter([
            strtolower((string) ($user?->job_title_code ?? '')),
            strtolower((string) ($user?->job_title ?? '')),
        ]);

        return count(array_intersect($allowedRoles, $userRoles)) > 0;
    }
}
