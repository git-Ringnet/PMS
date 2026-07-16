<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sort_option',
        'night_view',
        'show_notes',
    ];

    protected $casts = [
        'night_view' => 'boolean',
        'show_notes' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
