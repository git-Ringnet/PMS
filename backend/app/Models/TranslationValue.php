<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class TranslationValue extends Model
{
    use UsesSystemConnection;

    protected $table = 'translation_values';

    protected $fillable = ['entry_id', 'lang_code', 'default_value', 'value', 'updated_by'];
}
