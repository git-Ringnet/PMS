<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nationality extends Model
{
    use HasFactory;

    protected $fillable = [
        'nationality_id',
        'nationality_id2',
        'nationality_id_number',
        'nationality_name',
        'nationality_name_en',
        'nationality_id_uid',
        'nationality_id_shift',
        'nationality_code',
        'continent_code',
        'asm_id',
        'asm_code',
        'asm_name',
        'asm_description',
    ];

    protected $casts = [
        'nationality_id_number' => 'integer',
        'nationality_id_uid'    => 'integer',
        'nationality_id_shift'  => 'integer',
        'continent_code'        => 'integer',
        'asm_id'                => 'integer',
    ];
}
