<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomNightBill extends Model
{
    protected $table = 'room_night_bills';
    protected $primaryKey = 'bill_id';
    public $incrementing = false;

    protected $fillable = [
        'bill_id',
        'adult',
        'child',
        'is_room_night',
        'breakfast_amount',
        'extrabed_amount',
        'date',
        'room',
        'room_type_id',
        'room_kind_id',
        'breakfast',
        'extra_bed',
        'rate_code',
        'rate',
    ];

    protected $casts = [
        'date'             => 'date',
        'is_room_night'    => 'integer',
        'adult'            => 'integer',
        'child'            => 'integer',
        'breakfast'        => 'integer',
        'extra_bed'        => 'integer',
        'rate'             => 'decimal:2',
        'breakfast_amount' => 'decimal:2',
        'extrabed_amount'  => 'decimal:2',
    ];

    public function serviceBill()
    {
        return $this->belongsTo(ServiceBill::class, 'bill_id', 'Ma');
    }
}
