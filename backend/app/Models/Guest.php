<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastGuest = self::where('id', 'like', 'K%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->first();
                $nextNum = 1;
                if ($lastGuest && preg_match('/^K(\d+)$/', $lastGuest->id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                }
                $model->id = 'K' . str_pad($nextNum, 9, '0', STR_PAD_LEFT);
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

            if (!empty($model->guest_type) && !is_numeric($model->guest_type)) {
                if (strtoupper($model->guest_type) === 'FIT') {
                    $gt = GuestType::where('code', 'RegularGuest')->first();
                } else {
                    $gt = GuestType::where('code', $model->guest_type)->orWhere('name', $model->guest_type)->first();
                }
                if ($gt) {
                    $model->guest_type = (string) $gt->id;
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
    }

    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'full_name', 'title', 'id_type', 'id_number', 'id_issue_date',
        'passport_number', 'passport_expiry', 'dob', 'gender', 'nationality_code',
        'phone', 'email', 'address', 'guest_type',
        'province', 'district', 'ward',
        'residence_type', 'temp_residence_to',
        'visa_no', 'entry_date', 'visa_expiry_date',
        'entry_purpose', 'border_gate', 'occupation',
        'note', 'guest_status', 'avatar',
    ];

    protected $casts = [
        'dob'              => 'date',
        'id_issue_date'    => 'date',
        'passport_expiry'  => 'date',
        'temp_residence_to'=> 'date',
        'entry_date'       => 'date',
        'visa_expiry_date' => 'date',
        'gender'           => 'integer',
        'guest_status'     => 'integer',
    ];


    public function bookingRoomGuests()
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
