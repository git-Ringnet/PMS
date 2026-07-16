<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'trading_name',
        'address',
        'tax_code',
        'phone',
        'email',
        'customer_source_id',
        'market_id',
        'sync_acc',
        'max_debt',
        'bank_account',
        'booker_id',
        'sales_person_id',
        'rate_code',
        'branch_id',
        'is_active',
    ];

    protected $casts = [
        'sync_acc' => 'boolean',
        'is_active' => 'boolean',
        'max_debt' => 'decimal:2',
    ];

    /**
     * Auto-generate company code (CTY0001, CTY0002...) on creating.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->code)) {
                $lastCompany = static::orderBy('id', 'desc')->first();
                $nextNumber = $lastCompany ? ((int) substr($lastCompany->code, 3)) + 1 : 1;
                $company->code = 'CTY' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customerSource(): BelongsTo
    {
        return $this->belongsTo(CustomerSource::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(Booker::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }
}
