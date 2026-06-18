<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfoBusiness extends Model
{
    use HasFactory;

    protected $table = 'info_businesses';

    protected $fillable = [
        'company_name',
        'bank_name',
        'chairman',
        'phone',
        'email',
        'director',
        'address',
        'system_branch_id',
        'chief_accountant',
        'logo_url',
    ];

    public function systemBranch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'system_branch_id');
    }
}
