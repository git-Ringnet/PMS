<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemUpdate extends Model
{
    protected $fillable = ['update_date', 'content', 'created_by'];

    protected $casts = [
        'update_date' => 'date',
    ];
}
