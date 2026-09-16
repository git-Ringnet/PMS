<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SalesInvoice — Hóa đơn bán hàng
 * Quản lý tổng doanh thu bán hàng theo ngày thanh toán / quyết toán cấn trừ cọc và dịch vụ.
 */
class SalesInvoice extends Model
{
    use HasFactory;

    protected $table = 'sales_invoices';

    protected $fillable = [
        'legacy_id',
        'bill_id',
        'invoice_date',
        'payment_date',
        'open_time',
        'room',
        'guest_room_id',
        'currency',
        'original_rate',
        'service_charge_amount',
        'special_tax',
        'tax',
        'discount',
        'amount',
        'exchange_rate',
        'outlet',
        'department',
        'username',
        'ca',
        'status',
        'note',
        'booking_id',
        'booking_room_id',
        'guest_id',
        'company_id',
        'payment_code',
        'guest_name',
        'legacy_rental_room_id',
        'legacy_booking_id',
        'legacy_payment_id',
    ];

    protected $casts = [
        'invoice_date'          => 'datetime',
        'payment_date'          => 'datetime',
        'legacy_id'             => 'integer',
        'booking_id'            => 'integer',
        'company_id'            => 'integer',
        'legacy_booking_id'     => 'integer',
        'legacy_payment_id'     => 'integer',
        'status'                => 'integer',
        'original_rate'         => 'decimal:6',
        'service_charge_amount' => 'decimal:6',
        'special_tax'           => 'decimal:6',
        'tax'                   => 'decimal:6',
        'discount'              => 'decimal:6',
        'amount'                => 'decimal:6',
        'exchange_rate'         => 'decimal:6',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id', 'id');
    }

    public function serviceBills(): HasMany
    {
        return $this->hasMany(ServiceBill::class, 'InvoiceId', 'id');
    }
}
