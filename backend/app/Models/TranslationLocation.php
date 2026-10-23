<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class TranslationLocation extends Model
{
    use UsesSystemConnection;

    public $timestamps = false;

    protected $table = 'translation_locations';

    protected $fillable = ['entry_id', 'area', 'route', 'sort_order', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
