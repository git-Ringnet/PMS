<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            [
                'employee_code' => 'NB0058',
                'name' => 'Thảo Vy',
                'job_title_code' => 'RL017',
                'job_title' => 'Trưởng Bộ Phận',
                'department_code' => 'FO',
                'department' => 'BỘ PHẬN LỄ TÂN',
                'birth_date' => '2026-06-09',
                'start_date' => '2026-06-16',
                'phone' => '0901234558',
                'email' => 'thaovy275961@gmail.com',
                'address' => 'Nha Trang, Khánh Hòa',
                'is_active_user' => true,
            ],
            [
                'employee_code' => 'NB0057',
                'name' => 'HKM',
                'job_title_code' => 'RL016',
                'job_title' => 'Trưởng HK',
                'department_code' => 'HK',
                'department' => 'BỘ PHẬN BUỒNG PHÒNG',
                'birth_date' => '2026-05-20',
                'start_date' => '2026-06-16',
                'phone' => '0901234557',
                'email' => 'HKM@gmail.com',
                'address' => 'Nha Trang, Khánh Hòa',
                'is_active_user' => true,
            ],
            [
                'employee_code' => 'NB0056',
                'name' => 'FBM',
                'job_title_code' => 'RL015',
                'job_title' => 'Trưởng nhà hàng',
                'department_code' => 'FB',
                'department' => 'BỘ PHẬN NHÀ HÀNG',
                'birth_date' => '2026-05-20',
                'start_date' => '2026-06-16',
                'phone' => '0901234556',
                'email' => 'FBM@gmail.com',
                'address' => 'Nha Trang, Khánh Hòa',
                'is_active_user' => true,
            ],
            [
                'employee_code' => 'NB0055',
                'name' => 'DEMO NAVY',
                'job_title_code' => 'RL001',
                'job_title' => 'Tổng giám đốc',
                'department_code' => 'MGMT',
                'department' => 'BỘ PHẬN QUẢN LÝ',
                'birth_date' => '2026-03-16',
                'start_date' => '2026-06-16',
                'phone' => '0901234555',
                'email' => 'demonavy@gmail.com',
                'address' => 'Nha Trang, Khánh Hòa',
                'is_active_user' => true,
            ],
        ];

        foreach ($employees as $emp) {
            User::updateOrCreate(
                ['email' => $emp['email']],
                [
                    'employee_code' => $emp['employee_code'],
                    'name' => $emp['name'],
                    'job_title_code' => $emp['job_title_code'],
                    'job_title' => $emp['job_title'],
                    'department_code' => $emp['department_code'],
                    'department' => $emp['department'],
                    'birth_date' => $emp['birth_date'],
                    'start_date' => $emp['start_date'],
                    'phone' => $emp['phone'],
                    'address' => $emp['address'],
                    'is_active_user' => $emp['is_active_user'],
                    'password' => Hash::make('password123'),
                ]
            );
        }
    }
}
