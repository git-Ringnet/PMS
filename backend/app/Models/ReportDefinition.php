<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDefinition extends Model
{
    protected $fillable = [
        'code',
        'name',
        'group',
        'description',
        'report_data_source_id',
        'parameter_ui_schema',
        'sort_order',
        'is_active',
        'show_in_menu',
        'menu_locations',
        'menu_top_order',
        'menu_group_order',
        'menu_item_order',
    ];

    protected $casts = [
        'parameter_ui_schema' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'show_in_menu' => 'boolean',
        'menu_locations' => 'array',
        'menu_top_order' => 'integer',
        'menu_group_order' => 'integer',
        'menu_item_order' => 'integer',
    ];

    public function reportDataSource()
    {
        return $this->belongsTo(ReportDataSource::class);
    }

    public function templates()
    {
        return $this->belongsToMany(Template::class, 'report_definition_template')
            ->withPivot(['is_default', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('templates.name');
    }
}
