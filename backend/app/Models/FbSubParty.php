<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FbSubParty extends Model
{
    protected $table = 'fb_sub_parties';

    protected $fillable = [
        'party_id',
        'booking_code',
        'arrival_date',
        'arrival_time',
        'departure_time',
        'adults',
        'children',
        'tables',
        'extra',
        'outlet',
        'location',
        'party_type',
        'group_code',
        'note',
        'status',
    ];

    protected $casts = [
        'arrival_date' => 'date:Y-m-d',
        'adults' => 'integer',
        'children' => 'integer',
        'tables' => 'integer',
        'extra' => 'integer',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(FbParty::class, 'party_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FbPartyItem::class, 'sub_party_id');
    }

    public function outletModel(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet');
    }
}
