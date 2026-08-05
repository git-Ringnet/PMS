<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDebtSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id', 'payment_date', 'payment_time', 'payment_method_id',
        'amount', 'currency', 'description', 'edit_flag', 'created_by',
        'updated_by', 'deleted_by', 'deleted_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_time' => 'string',
        'amount' => 'decimal:2',
        'edit_flag' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'code');
    }
}
