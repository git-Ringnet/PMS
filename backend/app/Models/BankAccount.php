<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'bank_account_number',
        'accounting_account',
        'currency_code',
        'bank_name',
        'opened_on',
        'closed_on',
        'description',
        'is_intermediary',
        'is_active',
    ];

    protected $casts = [
        'opened_on' => 'date:Y-m-d',
        'closed_on' => 'date:Y-m-d',
        'is_intermediary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        // Counts and deletion checks must include historical/soft-deleted
        // payment rows so an account cannot be reused or removed while its
        // ledger history is still linked.
        return $this->hasMany(Payment::class, 'bank_account_id')->withTrashed();
    }
}
