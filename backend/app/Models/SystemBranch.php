<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemBranch extends Model
{
    use HasFactory;

    protected $table = 'system_branches';

    protected $fillable = [
        'code',
        'name',
        'tax_code',
        'email',
        'phone',
        'address',
        'accounting_month',
        'accounting_year',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'accounting_month' => 'integer',
        'accounting_year' => 'integer',
    ];

    public function businessInfos(): HasMany
    {
        return $this->hasMany(InfoBusiness::class, 'system_branch_id');
    }
}
