<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchTotal extends Model
{
    use HasFactory;

    protected $table = 'branches_total';

    protected $fillable = [
        'code',
        'name',
        'api_url',
        'api_report_url',
        'is_master',
    ];

    protected $casts = [
        'is_master' => 'boolean',
    ];
}
