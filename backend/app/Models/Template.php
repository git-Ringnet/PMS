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
}
