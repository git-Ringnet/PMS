<?php

namespace App\Services;

use App\Models\HotelConfig;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\User;

class HistoricalDateOperationPermissionService
{
    public function allows(?User $user, ?int $branchId = null): bool
    {
        if (!$user) {
            return false;
        }

        $configured = trim((string) HotelConfig::where('name', 'RuleUserCorrectOrPostBillPaymentOldDay')->value('value'));
        if ($configured === '') {
            return false;
        }

        $allowed = preg_split('/[,;|]+/', strtolower($configured), -1, PREG_SPLIT_NO_EMPTY);
        $identifiers = [
            strtolower((string) $user->username),
            strtolower((string) ($user->job_title_code ?? '')),
            strtolower((string) ($user->job_title ?? '')),
        ];

        $applicationCode = strtoupper(config('database_domains.default_application_code'));
        $assignments = $user->userBranchPositions()
            ->where('application_code', $applicationCode)
            ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
            ->get();

        if ($assignments->isNotEmpty()) {
            $roleIds = $assignments->map(fn ($assignment) => PositionBranchRole::query()
                ->where('position_id', $assignment->position_id)
                ->where('system_branch_id', $assignment->system_branch_id)
                ->where('application_code', $assignment->application_code)
                ->where('is_active', true)
                ->value('role_id'))
                ->filter()
                ->unique();

            $identifiers = array_merge($identifiers, Role::whereIn('id', $roleIds)
                ->pluck('code')
                ->map(fn ($code) => strtolower((string) $code))
                ->all());
        } else {
            $identifiers = array_merge($identifiers, $user->roles()
                ->when($branchId, fn ($query) => $query->where(function ($nested) use ($branchId) {
                    $nested->whereNull('user_roles.system_branch_id')
                        ->orWhere('user_roles.system_branch_id', $branchId);
                }))
                ->pluck('roles.code')
                ->map(fn ($code) => strtolower((string) $code))
                ->all());
        }

        return count(array_intersect($allowed, array_filter($identifiers))) > 0;
    }
}