<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbPartyPayment extends Model
{
    protected $table = 'fb_party_payments';

    protected $fillable = [
        'party_id',
        'sub_party_id',
        'payment_date',
        'payment_method',
        'amount',
        'note',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'amount' => 'float',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(FbParty::class, 'party_id');
    }

    public function subParty(): BelongsTo
    {
        return $this->belongsTo(FbSubParty::class, 'sub_party_id');
    }
}
