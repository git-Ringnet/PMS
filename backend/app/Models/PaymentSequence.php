<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSequence extends Model
{
    protected $fillable = ['sequence_key', 'current_value'];

    protected $casts = [
        'current_value' => 'integer',
    ];
}
