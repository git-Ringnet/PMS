<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbPartyItem extends Model
{
    protected $table = 'fb_party_items';

    protected $fillable = [
        'sub_party_id',
        'product_id',
        'name',
        'quantity',
        'unit',
        'price',
        'discount',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'discount' => 'float',
    ];

    public function subParty(): BelongsTo
    {
        return $this->belongsTo(FbSubParty::class, 'sub_party_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FbProduct::class, 'product_id');
    }
}
