<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FbParty extends Model
{
    protected $table = 'fb_parties';

    protected $fillable = [
        'party_code',
        'party_name',
        'arrival_date',
        'confirmation_type',
        'confirmation_date',
        'sale_staff',
        'company',
        'customer',
        'email',
        'note',
        'vat_note',
        'status',
    ];

    protected $casts = [
        'arrival_date' => 'date:Y-m-d',
        'confirmation_date' => 'date:Y-m-d',
    ];

    public function subParties(): HasMany
    {
        return $this->hasMany(FbSubParty::class, 'party_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FbPartyPayment::class, 'party_id');
    }
}
