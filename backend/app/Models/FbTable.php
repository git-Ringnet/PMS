<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbTable extends Model
{
    use HasFactory;

    protected $table = 'fb_tables';

    protected $fillable = [
        'table_code',
        'name',
        'location_id',
        'row_index',
        'col_index',
        'max_seats',
        'status',
        'image',
        'note',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'row_index' => 'integer',
        'col_index' => 'integer',
        'max_seats' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(FbLocation::class, 'location_id', 'id');
    }
}
