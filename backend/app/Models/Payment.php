<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Payment — Đặt cọc & Thanh toán
 * Theo nghiệp vụ mục 4.9 — PLAN_NGHIEP_VU_DAT_PHONG.md
 *
 * pack2 = "DPR"  → dòng đặt cọc
 * pack4 = "AP"   → advance payment
 * edit_flag = 1  → dòng đã hủy/đối trừ
 * reversal_ref   → ID dòng đối trừ tương ứng
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    // Trạng thái cọc
    const STATUS_PENDING  = 1; // Chưa thanh toán
    const STATUS_PAID     = 2; // Đã thanh toán
    const STATUS_DELETED  = 3; // Đã xóa

    // Loại cọc
    const PACK2_DEPOSIT   = 'DPR'; // Đặt cọc
    const PACK4_ADVANCE   = 'AP';  // Advance payment

    protected $fillable = [
        'booking_id',
        'booking_room_id',
        'guest_id',
        'company_id',
        'date',
        'open_time',
        'guest_display',
        'description',
        'amount',
        'total_amount_before_split',
        'pack2',
        'pack4',
        'folio_id',
        'payment_id',
        'reversal_ref',
        'payment_method_id',
        'debit_account',
        'vat_number',
        'serial',
        'invoice_number',
        'status',
        'edit_flag',
        'department_id',
        'outlet',
        'username',
        'shift',
        'created_by',
        'updated_by',
        'image_path',
    ];

    protected $casts = [
        'date'                       => 'date',
        'amount'                     => 'decimal:2',
        'total_amount_before_split'  => 'decimal:2',
        'status'                     => 'integer',
        'edit_flag'                  => 'integer',
    ];

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'code');
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    // =========================================
    // HELPERS
    // =========================================

    /** Kiểm tra đây là dòng đặt cọc */
    public function isDeposit(): bool
    {
        return $this->pack2 === self::PACK2_DEPOSIT;
    }

    /** Kiểm tra cọc chưa được thanh toán */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && is_null($this->payment_id);
    }

    /** Kiểm tra đây là dòng đã đối trừ */
    public function isReversed(): bool
    {
        return $this->edit_flag === 1;
    }
}
