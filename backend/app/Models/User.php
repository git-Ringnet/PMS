<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'username', 'email', 'password', 'employee_code', 'department_code', 'department',
    'job_title_code', 'job_title', 'birth_date', 'start_date', 'phone', 'address',
    'is_active_user', 'signature_url', 'primary_branch_id', 'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, UsesSystemConnection;

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_active_user'     => 'boolean',
            'must_change_password' => 'boolean',
            'birth_date'         => 'date:Y-m-d',
            'start_date'         => 'date:Y-m-d',
        ];
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getSignatureUrlAttribute($value)
    {
        return $value ? asset($value) : null;
    }

    // ─── Relationships ───────────────────────────────────────────

    public function setting()
    {
        return $this->hasOne(UserSetting::class);
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'primary_branch_id');
    }

    /** Tất cả chi nhánh user được phép truy cập */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(SystemBranch::class, 'user_branches', 'user_id', 'system_branch_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function userBranches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }

    /** Tất cả roles của user (có thể có pivot system_branch_id) */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('system_branch_id')
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function userBranchPositions(): HasMany
    {
        return $this->hasMany(UserBranchPosition::class);
    }

    public function warehousePermissions(): HasMany
    {
        return $this->hasMany(UserWarehousePermission::class);
    }

    // ─── Permission Helpers ──────────────────────────────────────

    /**
     * Tổng hợp tất cả permissions từ vị trí công việc theo chi nhánh (RBAC mới).
     * Chỉ fallback sang user_roles cũ nếu user hoàn toàn chưa có cấu hình vị trí công việc nào trong schema mới.
     */
    public function allPermissions(?int $branchId = null, ?string $applicationCode = null): \Illuminate\Support\Collection
    {
        $applicationCode = strtoupper($applicationCode ?: config('database_domains.default_application_code'));

        if ($this->isSuperAdmin()) {
            return Permission::pluck('code')->unique()->values();
        }

        // 1. Kiểm tra xem user đã được cấu hình trong Schema mới (user_branch_positions) hay chưa
        $hasNewAssignments = $this->userBranchPositions()
            ->where('application_code', $applicationCode)
            ->exists();

        if ($hasNewAssignments) {
            $positionQuery = $this->userBranchPositions()
                ->where('application_code', $applicationCode)
                ->when($branchId, fn($q) => $q->where('system_branch_id', $branchId))
                ->get();

            $permissions = collect();
            foreach ($positionQuery as $ubp) {
                $roleId = PositionBranchRole::where('position_id', $ubp->position_id)
                    ->where('system_branch_id', $ubp->system_branch_id)
                    ->where('application_code', $ubp->application_code)
                    ->where('is_active', true)
                    ->value('role_id');

                if ($roleId) {
                    $permCodes = BranchRolePermission::where('system_branch_id', $ubp->system_branch_id)
                        ->where('role_id', $roleId)
                        ->join('permissions', 'branch_role_permissions.permission_id', '=', 'permissions.id')
                        ->pluck('permissions.code');

                    $permissions = $permissions->concat($permCodes);
                }
            }

            // User đã thuộc hệ thống RBAC mới: trả về chính xác quyền tại chi nhánh, tuyệt đối không fallback sang role cũ
            return $permissions->unique()->values();
        }

        // 2. Chỉ Fallback sang Schema cũ nếu user hoàn toàn chưa có cấu hình vị trí theo schema mới
        $roles = $this->roles()
            ->with('permissions')
            ->when($branchId, fn($q) => $q->where(function ($q2) use ($branchId) {
                $q2->whereNull('user_roles.system_branch_id')
                   ->orWhere('user_roles.system_branch_id', $branchId);
            }))
            ->get();

        return $roles->flatMap(fn($r) => $r->permissions->pluck('code'))->unique()->values();
    }

    public function hasPermission(string $code, ?int $branchId = null, ?string $applicationCode = null): bool
    {
        // Super admin có tất cả quyền
        if ($this->isSuperAdmin()) {
            return true;
        }
        $applicationCode ??= Permission::query()->where('code', $code)->value('application_code');

        return $this->allPermissions($branchId, $applicationCode)->contains($code);
    }

    public function allPermissionsForBranch(?int $branchId = null): \Illuminate\Support\Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('code')->unique()->values();
        }

        $applicationCodes = $this->userBranchPositions()
            ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
            ->pluck('application_code')
            ->map(fn ($code) => strtoupper($code))
            ->unique();

        if ($applicationCodes->isEmpty()) {
            return $this->allPermissions($branchId);
        }

        return $applicationCodes
            ->flatMap(fn ($applicationCode) => $this->allPermissions($branchId, $applicationCode))
            ->unique()
            ->values();
    }

    public function canPerformHistoricalDateActions(?int $branchId = null, ?string $applicationCode = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $applicationCode = strtoupper($applicationCode ?: config('database_domains.default_application_code'));
        $assignments = $this->userBranchPositions()
            ->where('application_code', $applicationCode)
            ->when($branchId, fn ($query) => $query->where('system_branch_id', $branchId))
            ->get();

        if ($assignments->isNotEmpty()) {
            $roleIds = $assignments->map(function ($assignment) {
                return PositionBranchRole::query()
                    ->where('position_id', $assignment->position_id)
                    ->where('system_branch_id', $assignment->system_branch_id)
                    ->where('application_code', $assignment->application_code)
                    ->where('is_active', true)
                    ->value('role_id');
            })->filter()->unique();

            return Role::query()
                ->whereIn('id', $roleIds)
                ->where('allow_historical_date_actions', true)
                ->exists();
        }

        return $this->roles()
            ->when($branchId, fn ($query) => $query->where(function ($nested) use ($branchId) {
                $nested->whereNull('user_roles.system_branch_id')
                    ->orWhere('user_roles.system_branch_id', $branchId);
            }))
            ->where('allow_historical_date_actions', true)
            ->exists();
    }

    public function hasBranchAccess(int $branchId): bool
    {
        // Super admin có quyền tất cả chi nhánh
        if ($this->isSuperAdmin()) {
            return true;
        }
        return $this->userBranchPositions()->where('system_branch_id', $branchId)->exists()
            || $this->userBranches()->where('system_branch_id', $branchId)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('code', 'super_admin')->exists()
            || $this->userBranchPositions()
                ->join('position_branch_roles as pbr', function ($join) {
                    $join->on('pbr.position_id', '=', 'user_branch_positions.position_id')
                        ->on('pbr.system_branch_id', '=', 'user_branch_positions.system_branch_id')
                        ->on('pbr.application_code', '=', 'user_branch_positions.application_code');
                })
                ->join('roles as assigned_role', 'assigned_role.id', '=', 'pbr.role_id')
                ->where('pbr.is_active', true)
                ->where('assigned_role.code', 'super_admin')
                ->exists();
    }
}
