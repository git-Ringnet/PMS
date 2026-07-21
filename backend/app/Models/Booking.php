<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['booking_code'];

    public function getBookingCodeAttribute()
    {
        $setting = HotelSetting::first();
        $prefix = strtoupper($setting?->prefix_booking_id ?? 'GAL');
        return $prefix . $this->id;
    }

    protected $fillable = [
        'booking_name',
        'arrival_date',
        'departure_date',
        'num_of_days',
        'booking_date',
        'booking_time',
        'confirm_date',
        'expired_date',
        'arrival_flight',
        'arrival_flight_date',
        'departure_flight',
        'departure_flight_date',
        'status',
        'registration_status_id',
        'color',
        'is_git',
        'is_day_use',
        'breakfast_included',
        'has_vat',
        'company_id',
        'market_id',
        'customer_source_id',
        'branch_id',
        'booker_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'payment_method_id',
        'payment_value',
        'card_no',
        'card_holder',
        'card_cvv',
        'card_expired',
        'commission',
        'voucher_info',
        'external_booking_code',
        'event_code',
        'note',
        'special_requests',
        'sales_person',
        'created_by',
        'updated_by',
        'module',
        'edit_count',
        'edit_message',
        'edit_date',
        'shuttle_info',
        'deposit_details',
    ];

    protected $casts = [
        'arrival_date'         => 'date',
        'departure_date'       => 'date',
        'booking_date'         => 'date',
        'confirm_date'         => 'date',
        'expired_date'         => 'date',
        'arrival_flight_date'  => 'datetime',
        'departure_flight_date'=> 'datetime',
        'edit_date'            => 'datetime',
        'is_git'               => 'boolean',
        'is_day_use'           => 'boolean',
        'breakfast_included'   => 'boolean',
        'has_vat'              => 'boolean',
        'status'               => 'integer',
        'num_of_days'          => 'integer',
        'edit_count'           => 'integer',
        'payment_value'        => 'decimal:2',
        'commission'           => 'decimal:2',
        'shuttle_info'         => 'array',
        'deposit_details'      => 'array',
    ];

    // =========================================
    // STATUS CONSTANTS — Tình trạng vận hành phòng
    // =========================================
    const STATUS_RESERVATION = 0;   // Đăng ký
    const STATUS_CHECKIN     = 1;   // Checked In
    const STATUS_CHECKOUT    = 2;   // Checked Out
    const STATUS_DELETED     = 3;   // Đã xóa
    const STATUS_NO_SHOW     = 4;   // No Show
    const STATUS_TRANSFER    = 100; // Chuyển phòng

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function registrationStatus()
    {
        return $this->belongsTo(RegistrationStatus::class);
    }

    public function bookingStatus()
    {
        return $this->belongsTo(BookingStatus::class, 'status', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function customerSource()
    {
        return $this->belongsTo(CustomerSource::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function booker()
    {
        return $this->belongsTo(Booker::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function children()
    {
        return $this->hasMany(BookingChild::class);
    }

    public function cancelLogs()
    {
        return $this->hasMany(BookingCancelLog::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
