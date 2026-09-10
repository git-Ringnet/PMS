<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal invoice header projection for legacy SP3003 data.
 *
 * Legacy identifiers intentionally remain scalar values until the import
 * mapping for invoices, payments, bookings, and rooms is verified.
 */
class SalesInvoice extends Model
{
    use HasFactory;

    protected $table = 'sales_invoices';

    protected $fillable = [
        'legacy_id',
        'bill_id',
        'invoice_date',
        'room',
        'legacy_rental_room_id',
        'legacy_booking_id',
        'legacy_payment_id',
        'outlet',
        'username',
        'status',
        'amount',
        'currency',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'legacy_id' => 'integer',
        'legacy_booking_id' => 'integer',
        'legacy_payment_id' => 'integer',
        'status' => 'integer',
        'amount' => 'decimal:6',
    ];
}
