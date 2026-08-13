<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HkAssignment extends Model
{
    protected $table = 'hk_assignments';

    protected $fillable = ['work_date', 'shift_id', 'notes', 'created_by'];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(HkAssignmentGroup::class, 'assignment_id')->orderBy('sort_order');
    }
}
