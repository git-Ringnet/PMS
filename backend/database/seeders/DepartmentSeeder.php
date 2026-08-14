<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'FO', 'name' => 'Reception/ Lê Tân'],
            ['code' => 'HK', 'name' => 'House Keeping/Buồng Phòng'],
            ['code' => 'FB', 'name' => 'Restaurant/Nhà Hàng'],
            ['code' => 'SP', 'name' => 'Spa'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                [...$department, 'show' => 1]
            );
        }
    }
}
