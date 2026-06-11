<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'max_adults',
    ];

    protected $casts = [
        'max_adults' => 'integer',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function standardRates(): HasMany
    {
        return $this->hasMany(StandardRate::class);
    }
}
