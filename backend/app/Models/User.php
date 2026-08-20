<?php

namespace App\Models;

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
    'is_active_user', 'signature_url', 'primary_branch_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'mysql_system';

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_active_user'     => 'boolean',
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

    // ─── Permission Helpers ──────────────────────────────────────

    /**
     * Tổng hợp tất cả permissions từ tất cả roles của user.
     * Có thể filter theo branch_id nếu cần.
     */
    public function allPermissions(?int $branchId = null): \Illuminate\Support\Collection
    {
        $roles = $this->roles()
            ->with('permissions')
            ->when($branchId, fn($q) => $q->where(function ($q2) use ($branchId) {
                $q2->whereNull('user_roles.system_branch_id')
                   ->orWhere('user_roles.system_branch_id', $branchId);
            }))
            ->get();

        return $roles->flatMap(fn($r) => $r->permissions->pluck('code'))->unique()->values();
    }

    public function hasPermission(string $code, ?int $branchId = null): bool
    {
        // Super admin có tất cả quyền
        if ($this->roles()->where('code', 'super_admin')->exists()) {
            return true;
        }
        return $this->allPermissions($branchId)->contains($code);
    }

    public function hasBranchAccess(int $branchId): bool
    {
        // Super admin có quyền tất cả chi nhánh
        if ($this->roles()->where('code', 'super_admin')->exists()) {
            return true;
        }
        return $this->userBranches()->where('system_branch_id', $branchId)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('code', 'super_admin')->exists();
    }
}
