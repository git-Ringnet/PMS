<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory, UsesSystemConnection;

    protected $fillable = [
        'user_id',
        'sort_option',
        'night_view',
        'show_notes',
        'settings',
    ];

    protected $casts = [
        'night_view' => 'boolean',
        'show_notes' => 'boolean',
        'settings'   => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
