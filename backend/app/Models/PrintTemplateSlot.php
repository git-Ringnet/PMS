<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplateSlot extends Model
{
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected $fillable = [
        'code',
        'group',
        'name',
        'description',
        'template_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
