<?php

namespace App\Services;

use App\Models\HotelConfig;
use App\Models\PositionBranchRole;
use App\Models\User;

/**
 * Role allow-lists used by Checkout's legacy-compatible special operations.
 * These rules are intentionally separate from general route permissions.
 */
class CheckoutRoleConfigService
{
    public function isEnabled(string $configName): bool
    {
        return trim((string) HotelConfig::query()->where('name', $configName)->value('value')) === '1';
    }

    /**
     * Check an exact role/job-title code from one or more HotelConfig allow-lists.
     * Missing, blank, or `0` role lists deny access.
     *
     * @param array<int, string> $configNames
     */
    public function userHasConfiguredRole(?User $user, array $configNames, ?int $branchId): bool
    {
        if (!$user) {
            return false;
        }

        $allowed = collect($configNames)
            ->flatMap(fn (string $name) => preg_split('/[,;|]+/', (string) HotelConfig::query()->where('name', $name)->value('value'), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($code) => $this->normalize((string) $code))
            ->filter(fn (string $code) => $code !== '' && $code !== '0')
            ->unique()
            ->values();

        if ($allowed->isEmpty()) {
            return false;
        }

        return $allowed->intersect($this->userRoleIdentifiers($user, $branchId))->isNotEmpty();
    }

    public function canTransferPastDateToAnotherBooking(?User $user, ?int $branchId): bool
    {
        return $this->isEnabled('Bill_TransBillPastDateToAnotherBK')
            && $this->userHasConfiguredRole(
                $user,
                ['RoleUserAllowTransBillPastDateToAnotherBK', 'RUserAllowTransBillPastDateToAnotherBK'],
                $branchId
            );
    }

    public function canPostToCheckedOutTarget(?User $user, ?int $branchId): bool
    {
        return $this->isEnabled('AllowPostBillCheckedOutRoom')
            && $this->userHasConfiguredRole($user, ['RoleUserPostBillCheckedOutRoom'], $branchId);
    }

    public function canAdjustRoomRate(?User $user, ?int $branchId): bool
    {
        return $this->userHasConfiguredRole($user, ['RoleUserAdjustRoomRate'], $branchId);
    }

    public function canModifyRateBillService(?User $user, ?int $branchId): bool
    {
        return $this->userHasConfiguredRole($user, ['RuleUserModifyRateBillService'], $branchId);
    }

    /** Sale may edit only the configured Inhouse rate/departure fields when enabled. */
    public function allowSaleInhouseRateDeparture(?int $branchId = null): bool
    {
        return $this->isEnabled('AllowReserUpdateRate_DeptDateRoomInhouse');
    }

    /**
     * UI capability describing whether the current user has any checkout invoice
     * mutation permission in the resolved branch. Route middleware remains the
     * authority for each individual API mutation.
     */
    public function canMutateCheckoutInvoice(?User $user, ?int $branchId): bool
    {
        if (!$user) {
            return false;
        }

        foreach ([
            'fo.service.add',
            'fo.service.edit',
            'fo.service.delete',
            'fo.payment.create',
            'fo.checkout',
        ] as $permission) {
            if ($user->hasPermission($permission, $branchId)) {
                return true;
            }
        }

        return false;
    }

    /** S11 role grant is additional to the existing booking edit permission. */
    public function canUpdateCheckedOutBooking(?User $user, ?int $branchId): bool
    {
        return $user !== null
            && $user->hasPermission('fo.booking.edit', $branchId)
            && $this->hasExactConfiguredRoleCode($user, 'RoleUserUpdateCheckoutBooking', $branchId);
    }

    /**
     * Open a Do Not Move lock when the user has the canonical exact role.
     * In addition, the user who locked it is allowed to open if the parameter is unassigned.
     */
    public function canOpenDoNotMove(?User $user, ?int $branchId, int|string|null $lockOwnerUserId): bool
    {
        if (!$user || !$user->hasPermission('fo.booking.edit', $branchId)) {
            return false;
        }

        $configuredCodes = $this->getConfiguredRoleCodes('RoleUserOpenDoNotMove');
        if ($configuredCodes === []) {
            return $lockOwnerUserId !== null && (string) $user->getKey() === (string) $lockOwnerUserId;
        }

        return $this->hasExactConfiguredRoleCode($user, 'RoleUserOpenDoNotMove', $branchId);
    }

    public function getConfiguredRoleCodes(string $configName): array
    {
        $allowedCodes = preg_split(
            '/[,;|]+/',
            (string) HotelConfig::query()->where('name', $configName)->value('value'),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn ($code) => $this->normalize((string) $code),
            $allowedCodes
        ), fn (string $code) => $code !== '' && $code !== '0')));
    }

    private function hasExactConfiguredRoleCode(User $user, string $configName, ?int $branchId): bool
    {
        $allowedCodes = $this->getConfiguredRoleCodes($configName);
        if ($allowedCodes === []) {
            return false;
        }

        $applicationCode = strtoupper((string) config('database_domains.default_application_code', 'PMS'));
        $newAssignments = $user->userBranchPositions()
            ->where('application_code', $applicationCode)
            ->exists();

        if ($newAssignments) {
            $assignedRoleCodes = $user->userBranchPositions()
                ->where('application_code', $applicationCode)
                ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
                ->get()
                ->map(fn ($assignment) => PositionBranchRole::query()
                    ->where('position_id', $assignment->position_id)
                    ->where('system_branch_id', $assignment->system_branch_id)
                    ->where('application_code', $applicationCode)
                    ->where('is_active', true)
                    ->with('role:id,code')
                    ->first()?->role?->code)
                ->filter()
                ->map(fn ($code) => $this->normalize((string) $code));
        } else {
            $assignedRoleCodes = $user->roles()
                ->when($branchId, fn ($query) => $query->where(function ($nested) use ($branchId) {
                    $nested->whereNull('user_roles.system_branch_id')
                        ->orWhere('user_roles.system_branch_id', $branchId);
                }))
                ->pluck('roles.code')
                ->map(fn ($code) => $this->normalize((string) $code));
        }

        return $assignedRoleCodes->intersect($allowedCodes)->isNotEmpty();
    }

    /** @return array<int, string> */
    private function userRoleIdentifiers(User $user, ?int $branchId): array
    {
        $identifiers = array_filter([
            $this->normalize((string) $user->job_title_code),
            $this->normalize((string) $user->job_title),
            $this->normalize((string) $user->department_code),
            $this->normalize((string) $user->department),
        ]);

        $applicationCode = strtoupper((string) config('database_domains.default_application_code', 'PMS'));
        $hasNewAssignments = $user->userBranchPositions()
            ->where('application_code', $applicationCode)
            ->exists();

        if ($hasNewAssignments) {
            $assignments = $user->userBranchPositions()
                ->where('application_code', $applicationCode)
                ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
                ->with('position')
                ->get();

            foreach ($assignments as $assignment) {
                $identifiers[] = $this->normalize((string) $assignment->position?->code);
                $identifiers[] = $this->normalize((string) $assignment->position?->name);

                $role = PositionBranchRole::query()
                    ->where('position_id', $assignment->position_id)
                    ->where('system_branch_id', $assignment->system_branch_id)
                    ->where('application_code', $assignment->application_code)
                    ->where('is_active', true)
                    ->with('role')
                    ->first()?->role;

                $identifiers[] = $this->normalize((string) $role?->code);
                $identifiers[] = $this->normalize((string) $role?->name);
            }

            return array_values(array_unique(array_filter($identifiers)));
        }

        $legacyRoles = $user->roles()
            ->when($branchId, fn ($query) => $query->where(function ($nested) use ($branchId) {
                $nested->whereNull('user_roles.system_branch_id')
                    ->orWhere('user_roles.system_branch_id', $branchId);
            }))
            ->get(['roles.code', 'roles.name']);

        foreach ($legacyRoles as $role) {
            $identifiers[] = $this->normalize((string) $role->code);
            $identifiers[] = $this->normalize((string) $role->name);
        }

        return array_values(array_unique(array_filter($identifiers)));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
