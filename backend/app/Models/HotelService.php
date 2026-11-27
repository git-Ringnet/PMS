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

        // Dòng 543: mô tả bill đồng nhất "Tên dịch vụ + số phòng", vd "Dịch vụ phòng nghỉ 102"
        // (cùng mẫu với tiền phòng noshow "Dịch vụ phòng nghỉ noshow 102" - dòng 540).
        return self::describeForRoom($description, $roomNumber);
    }

    /** Dòng 543: ghép mô tả bill dịch vụ với số phòng theo mẫu "<tên dịch vụ> <số phòng>". */
    public static function describeForRoom(string $description, $roomNumber = null): string
    {
        $roomNumber = trim((string) ($roomNumber ?? ''));

        return trim($roomNumber !== '' ? $description . ' ' . $roomNumber : $description);
    }
}
