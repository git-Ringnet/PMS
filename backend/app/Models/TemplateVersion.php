<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'report_data_source_id',
        'parameter_defaults',
        'version',
        'content_json',
        'content_html',
        'css',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'content_json' => 'array',
        'parameter_defaults' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
