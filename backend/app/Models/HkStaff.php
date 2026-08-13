<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HkStaff extends Model
{
    protected $table = 'hk_staff';

    protected $fillable = ['name', 'is_active', 'is_hidden', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function groupStaff(): HasMany
    {
        return $this->hasMany(HkAssignmentGroupStaff::class, 'staff_id');
    }

    /**
     * Kiểm tra NV đã có phân công chưa (để chặn xóa)
     */
    public function hasAssignments(): bool
    {
        return $this->groupStaff()->exists();
    }
}
