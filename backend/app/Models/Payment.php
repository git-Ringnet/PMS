<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            $payment->user_id ??= Auth::id();
        });
    }

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
        'reason',
        'amount',
        'currency',
        'total_amount_before_split',
        'pack2',
        'pack4',
        'folio_id',
        'payment_id',
        'reversal_ref',
        'payment_method_id',
        'debit_account',
        'bank_account_id',
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
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function debtSettlements()
    {
        return $this->hasMany(PaymentDebtSettlement::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withTrashed();
    }

    /** Stable public URL for receipt images, while retaining image_path for legacy clients. */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        $path = (string) $this->image_path;
        if (preg_match('/^https?:\/\//i', $path)) {
            // Older rows may already contain the public URL. Keep external
            // URLs intact, but collapse local /storage URLs to a relative
            // path so a development APP_URL cannot send the browser to port
            // 80 instead of the API/storage origin.
            $parsed = parse_url($path);
            if (!empty($parsed['path']) && preg_match('#^/(?:storage|uploads)/#i', $parsed['path'])) {
                return $parsed['path'] . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
            }

            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#^(?:storage|public)/#i', '', $path);

        $url = Storage::disk('public')->url($path);
        if ((string) config('filesystems.disks.public.driver') === 'local') {
            $parsed = parse_url($url);
            if (!empty($parsed['path'])) {
                return $parsed['path'] . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
            }
        }

        return $url;
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
