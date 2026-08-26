<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'name',
        'report',
        'report_data_source_id',
        'parameter_defaults',
        'page_size',
        'page_orientation',
        'margin_top',
        'margin_bottom',
        'margin_left',
        'margin_right',
        'content_json',
        'content_html',
        'css',
        'is_default',
        'version',
    ];

    protected $casts = [
        'content_json' => 'array',
        'parameter_defaults' => 'array',
        'is_default' => 'boolean',
        'margin_top' => 'integer',
        'margin_bottom' => 'integer',
        'margin_left' => 'integer',
        'margin_right' => 'integer',
    ];

    public function versions()
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('created_at', 'desc');
    }

    public function reportDataSource()
    {
        return $this->belongsTo(ReportDataSource::class);
    }

    public function reportDefinitions()
    {
        return $this->belongsToMany(ReportDefinition::class, 'report_definition_template')
            ->withPivot(['is_default', 'sort_order'])
            ->withTimestamps();
    }

    public function printTemplateSlots()
    {
        return $this->hasMany(PrintTemplateSlot::class);
    }
}
