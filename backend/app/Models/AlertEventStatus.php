<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertEventStatus extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_DONE = 1;

    protected $fillable = ['event_key', 'status', 'updated_by'];
}
