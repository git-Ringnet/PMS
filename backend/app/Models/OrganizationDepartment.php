<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationDepartment extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['code', 'name', 'phone', 'legacy_show', 'is_active', 'sort_order'];

    protected $casts = [
        'legacy_show' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('sort_order')->orderBy('name');
    }
}
