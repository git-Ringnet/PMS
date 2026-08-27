<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDataSource extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'source_type',
        'schema_name',
        'object_name',
        'parameter_schema',
        'field_schema',
        'sample_parameters',
        'max_rows',
        'is_active',
        'last_discovered_at',
    ];

    protected $casts = [
        'parameter_schema' => 'array',
        'field_schema' => 'array',
        'sample_parameters' => 'array',
        'max_rows' => 'integer',
        'is_active' => 'boolean',
        'last_discovered_at' => 'datetime',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function reportDefinitions()
    {
        return $this->hasMany(ReportDefinition::class);
    }
}
