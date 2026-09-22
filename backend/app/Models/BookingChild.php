<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingChild extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastChild = self::where('id', 'like', 'T%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->first();
                $nextNum = 1;
                if ($lastChild && preg_match('/^T(\d+)$/', $lastChild->id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                }
                $model->id = 'T' . str_pad($nextNum, 9, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function ($model) {
            if (empty($model->gender) && !empty($model->title)) {
                $matchedTitle = GuestTitle::where('name', $model->title)->orWhere('code', $model->title)->first();
                if ($matchedTitle) {
                    $model->gender = $matchedTitle->gender;
                } elseif (in_array(rtrim($model->title, '.'), ['Mr', 'Boy', 'Inf', 'Kid'])) {
                    $model->gender = 1;
                } elseif (in_array(rtrim($model->title, '.'), ['Ms', 'Mrs', 'Girl'])) {
                    $model->gender = 2;
                }
            }

            if (empty($model->nationality_code)) {
                $model->nationality_code = 'VNM';
            } elseif (strlen($model->nationality_code) === 2) {
                $upperNat = strtoupper($model->nationality_code);
                if ($upperNat === 'VN') {
                    $model->nationality_code = 'VNM';
                } elseif ($upperNat === 'RU') {
                    $model->nationality_code = 'RUS';
                } else {
                    $nat = Nationality::where('asm_code', $model->nationality_code)->first();
                    if ($nat && $nat->nationality_id) {
                        $model->nationality_code = $nat->nationality_id;
                    }
                }
            }

            if (!empty($model->residence_type) && !is_numeric($model->residence_type)) {
                $rt = ResidenceType::where('name', $model->residence_type)
                    ->orWhere('name_new_form', $model->residence_type)
                    ->first();
                if ($rt) {
                    $model->residence_type = (string) $rt->id;
                }
            }

            if (!empty($model->entry_purpose) && !is_numeric($model->entry_purpose)) {
                $ep = EntryPurpose::where('name', $model->entry_purpose)
                    ->orWhere('code', $model->entry_purpose)
                    ->first();
                if ($ep) {
                    $model->entry_purpose = (string) $ep->id;
                }
            }

            if (!empty($model->border_gate) && strlen($model->border_gate) > 5) {
                $bg = BorderGate::where('name', $model->border_gate)->first();
                if ($bg) {
                    $model->border_gate = $bg->code;
                }
            }
        });

        static::created(function ($model) {
            if (!empty($model->booking_room_id)) {
                BookingRoomChild::firstOrCreate(
                    [
                        'booking_child_id' => $model->id,
                        'booking_room_id' => $model->booking_room_id,
                    ],
                    ['status' => (int) ($model->child_status ?? 1)]
                );
            }
        });

        static::updated(function ($model) {
            if (!$model->wasChanged('child_status') || empty($model->booking_room_id)) {
                return;
            }

            $room = BookingRoom::find($model->booking_room_id);
            if (!$room) {
                return;
            }

            $status = (int) $model->child_status;
            $assignmentStatus = in_array($status, [0, 1], true) ? 1 : $status;
            $data = ['status' => $assignmentStatus];

            if ($status === BookingRoomGuest::STATUS_CHECKED_OUT) {
                $systemDate = app(\App\Services\RoomAvailabilityService::class)->getSystemDate();
                $data += [
                    'actual_checkout_date' => $systemDate->toDateString(),
                    'actual_checkout_time' => now()->format('H:i:s'),
                    'checkout_by' => auth()->user()?->username ?? 'system',
                ];
            } elseif (in_array($status, [0, 1], true)) {
                $data += [
                    'actual_checkout_date' => $room->departure_date->toDateString(),
                    'actual_checkout_time' => '12:00:00',
                    'checkout_by' => null,
                ];
            }

            BookingRoomChild::updateOrCreate(
                [
                    'booking_child_id' => $model->id,
                    'booking_room_id' => $model->booking_room_id,
                ],
                $data
            );
        });
    }

    protected $fillable = [
        'booking_id', 'booking_room_id', 'full_name', 'title',
        'dob', 'nationality_code', 'age_group', 'child_status',
        'id_type', 'id_number', 'id_issue_date', 'passport_number', 'passport_expiry', 'gender',
        'phone', 'email', 'address', 'province', 'district', 'ward',
        'residence_type', 'temp_residence_to', 'visa_no', 'entry_date', 'visa_expiry_date',
        'entry_purpose', 'border_gate', 'note',
    ];

    protected $casts = [
        'child_status'      => 'integer',
        'dob'               => 'date',
        'id_issue_date'     => 'date',
        'passport_expiry'   => 'date',
        'temp_residence_to' => 'date',
        'entry_date'        => 'date',
        'visa_expiry_date'  => 'date',
    ];


    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function breakfastDetails()
    {
        return $this->hasMany(BookingChildBreakfastDetail::class);
    }
}
