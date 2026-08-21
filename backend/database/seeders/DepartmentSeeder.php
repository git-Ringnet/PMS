<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Ẩn tất cả phòng ban khác
        Department::query()->update(['show' => 0]);

        $departments = [
            ['code' => 'FO', 'name' => 'BỘ PHẬN LỄ TÂN'],
            ['code' => 'HK', 'name' => 'BỘ PHẬN BUỒNG PHÒNG'],
            ['code' => 'SYS', 'name' => 'QUẢN TRỊ HỆ THỐNG'],
            ['code' => 'FB', 'name' => 'BỘ PHẬN F&B'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                [...$department, 'show' => 1]
            );
        }
    }
}
