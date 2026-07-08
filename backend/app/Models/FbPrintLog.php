<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'corder_code',
        'printer_name',
        'printer_type',
        'is_printed',
        'html',
        'printed_at'
    ];

    protected $casts = [
        'is_printed' => 'boolean',
        'printed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(FbOrder::class, 'order_id');
    }
}
