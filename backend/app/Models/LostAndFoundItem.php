<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LostAndFoundItem extends Model
{
    protected $casts = [
        'image' => 'array',
    ];

    protected $fillable = [
        'log_no',
        'item_found',
        'date_found',
        'who_found',
        'received',
        'date_handling',
        'method_handling',
        'delieved_handling',
        'received_handling',
        'remarks',
        'where_found',
        'guest_info',
        'storage_location',
        'date_reported',
        'status',
        'image',
        'created_by',
    ];
}
