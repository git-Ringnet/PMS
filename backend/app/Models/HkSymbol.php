<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HkSymbol extends Model
{
    protected $table = 'hk_symbols';

    protected $fillable = ['group', 'status_key', 'code', 'label', 'color', 'sort_order'];
}
