<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_code',
        'table_id',
        'name',
        'status',
        'customer_name',
        'customer_phone',
        'guest_count',
        'public_note',
        'internal_note',
        'promotion_id',
        'total_amount',
        'creator_id',
    ];

    public function items()
    {
        return $this->hasMany(FbOrderItem::class, 'order_id');
    }

    public function table()
    {
        return $this->belongsTo(FbTable::class, 'table_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
