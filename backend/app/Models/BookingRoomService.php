<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class BookingRoomService extends Model
{
    use HasFactory, SoftDeletes;

    /** Keep an explicitly allocated amount when splitting by a requested amount. */
    public bool $preserveTotalAmount = false;

    protected $fillable = [
        'booking_room_id',
        'guest_id',
        'service_bill_id',
        'service_bill_detail_no',
        'housekeeping_service_bill_id',
        'housekeeping_service_bill_detail_no',
        'service_code',
        'service_name',
        'service_date',
        'quantity',
        'rate',
        'total_amount',
        'department',
        'note',
        'tax',
        'service_charge',
        'unit',
        'folio',
        'is_room',
        'is_posted',
        'posted_at',
        'posted_by_employee_code',
        'created_by',
        'user_id',
        'updated_by',
        'deleted_at',
    ];

    protected static function boot()
    {
        parent::boot();

        // Tự động tính total_amount = quantity * rate khi tạo/cập nhật
        static::saving(function ($model) {
            $model->user_id ??= Auth::id();
            if (!$model->preserveTotalAmount) {
                $model->total_amount = floatval($model->quantity) * floatval($model->rate);
            }

            if ((int) $model->is_posted === 1 && !$model->posted_by_employee_code && Auth::user()) {
                $model->posted_by_employee_code = Auth::user()->employee_code;
            }
        });

        static::deleted(function ($model) {
            $note = strtolower($model->note ?? '');
            $serviceCode = \App\Models\HotelConfig::where('name', 'Booking_BFChildSetServiceId')->value('value') ?: 'BD';
            if ($model->service_code === $serviceCode && str_starts_with($note, 'phụ thu ăn sáng trẻ em:')) {
                $childName = trim(substr($model->note, strlen('Phụ thu ăn sáng trẻ em:')));
                if ($childName) {
                    $child = \App\Models\BookingChild::where('booking_room_id', $model->booking_room_id)
                        ->where('full_name', $childName)
                        ->first();
                    if ($child) {
                        $detail = \App\Models\BookingChildBreakfastDetail::where('booking_child_id', $child->id)
                            ->whereDate('service_date', $model->service_date)
                            ->first();
                        if ($detail) {
                            $bfRateChild = \App\Models\HotelConfig::where('name', 'BreakfastRateChild')->value('value');
                            $setting = \App\Models\HotelSetting::first();
                            $amount = (float) ($bfRateChild ?? $setting?->breakfast_child_rate ?? 0);
                            
                            $detail->update([
                                'is_extra_charge' => false,
                                'is_room' => true,
                                'amount' => $amount
                            ]);
                        }
                    }
                }
            }
        });
    }

    protected $casts = [
        'service_date'  => 'date',
        'quantity'      => 'decimal:6',
        'rate'          => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'is_room'       => 'integer',
        'folio'         => 'integer',
        'is_posted'     => 'integer',
        'posted_at'     => 'datetime',
    ];

    // Service code constants
    const CODE_ROOM        = 'RM'; // Tiền phòng
    const CODE_EXTRA_BED   = 'EB'; // Thêm giường
    const CODE_BF_CHILD    = 'BD'; // Phụ thu ăn sáng trẻ em

    public static function catalogCode(string $code): string
    {
        return (string) (\App\Models\HotelService::where('code', $code)->value('code') ?: $code);
    }

    public static function catalogName(string $code, string $fallback = ''): string
    {
        return (string) (\App\Models\HotelService::where('code', $code)->value('name') ?: $fallback);
    }

    public static function updateOrCreateForDate(array $identity, $serviceDate, array $values): self
    {
        $date = Carbon::parse($serviceDate)->toDateString();
        $query = static::query();

        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $service = $query->whereDate('service_date', $date)->first();
        if ($service) {
            $service->fill($values)->save();
            return $service;
        }

        return static::create([
            ...$identity,
            'service_date' => $date,
            ...$values,
        ]);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
