<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationEntry extends Model
{
    use UsesSystemConnection;

    protected $table = 'translation_entries';

    protected $fillable = ['source_hash', 'source', 'is_pattern', 'note'];

    protected $casts = [
        'is_pattern' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class, 'entry_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TranslationLocation::class, 'entry_id');
    }
}
