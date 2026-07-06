<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomLock extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_UNLOCKED = 2;

    protected $fillable = [
        'room_number',
        'start_date',
        'end_date',
        'reason',
        'maintenance_percent',
        'status',
        'username',
        'unlock_username',
        'unlocked_at',
        'lock_type',
        'is_active',
    ];

    protected $casts = [
        'maintenance_percent' => 'integer',
        'is_active' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    protected static $userNamesCache = [];

    public function getUsernameAttribute($value)
    {
        if (empty($value)) return '';
        
        if (isset(self::$userNamesCache[$value])) {
            return self::$userNamesCache[$value];
        }

        $user = \App\Models\User::where('username', $value)
            ->orWhere('employee_code', $value)
            ->first();
            
        if ($user) {
            self::$userNamesCache[$value] = $user->name;
            return $user->name;
        }

        return $value;
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_number', 'room_number');
    }
}
