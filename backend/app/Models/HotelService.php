<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelService extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'service_charge',
        'tax',
        'special_tax',
        'include_service_charge',
        'include_tax',
        'include_special_tax',
        'folio',
        'short_name',
        'unit',
        'price',
        'is_active',
        'department_id',
    ];

    protected $casts = [
        'include_service_charge' => 'boolean',
        'include_tax' => 'boolean',
        'include_special_tax' => 'boolean',
        'price' => 'float',
        'is_active' => 'boolean',
        'service_charge' => 'float',
        'tax' => 'float',
        'special_tax' => 'float',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class)
            ->withPivot('description')
            ->withTimestamps();
    }

    public static function taxProfile(?self $service): array
    {
        return [
            'service_charge' => (float) ($service?->service_charge ?? 0),
            'special_tax' => (float) ($service?->special_tax ?? 0),
            'tax' => (float) ($service?->tax ?? 0),
        ];
    }

    public function billDescription(?string $roomNumber = null, string $departmentCode = 'FO'): string
    {
        $department = $this->departments()
            ->where('departments.code', $departmentCode)
            ->first();
        $description = trim((string) ($department?->pivot?->description ?: $this->name));

        return $roomNumber
            ? $description . ' - Phòng ' . $roomNumber
            : $description;
    }
}
