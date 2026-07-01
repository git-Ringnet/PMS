<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbLocation extends Model
{
    use HasFactory;

    protected $table = 'fb_locations';

    // Disable auto-incrementing as id is char(10) from legacy DB
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'note',
        'is_active',
        'outlet_code',
        'color',
        'letter',
        'day_use',
        'provide1',
        'image',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_code', 'code');
    }
}
