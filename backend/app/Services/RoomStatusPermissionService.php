<?php

namespace App\Services;

use App\Models\HotelConfig;
use Illuminate\Http\Request;

class RoomStatusPermissionService
{
    public function canChange(Request $request): bool
    {
        $module = strtolower((string) $request->input('current_module', 'frontdesk'));

        if ($module === 'reservation') {
            return false;
        }

        if ($module === 'housekeeping') {
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
}
