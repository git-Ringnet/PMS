<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HkPrintCol extends Model
{
    protected $table = 'hk_print_cols';

    protected $fillable = ['template', 'label', 'parent_label', 'width', 'is_fixed', 'sort_order'];

    protected $casts = ['is_fixed' => 'boolean'];
}
