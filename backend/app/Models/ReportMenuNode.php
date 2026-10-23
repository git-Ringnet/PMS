<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMenuNode extends Model
{
    protected $fillable = ['location', 'parent_id', 'type', 'name', 'report_definition_id', 'sort_order'];

    protected $casts = [
        'parent_id' => 'integer',
        'report_definition_id' => 'integer',
        'sort_order' => 'integer',
    ];
}
