<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LostAndFoundItem extends Model
{
    protected $fillable = [
        'log_no',
        'item_found',
        'time_found',
        'date_found',
        'who_found',
        'received',
        'date_handling',
        'time_handling',
        'method_handling',
        'delieved_handling',
        'received_handling',
        'remarks',
        'where_found',
        'status',
        'image',
    ];
}
