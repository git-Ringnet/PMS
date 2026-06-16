<?php

namespace Database\Seeders;

use App\Models\SystemBranch;
use Illuminate\Database\Seeder;

class SystemBranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'code' => 'HKT1',
                'name' => 'Chi nhánh HKT 1',
                'tax_code' => '0312345671',
                'email' => 'hkt1@hktsolution.vn',
                'phone' => '02862828281',
                'address' => 'Nha Trang, Khánh Hòa',
            ],
            [
                'code' => 'HKT2',
                'name' => 'Chi nhánh HKT 2',
                'tax_code' => '0312345672',
                'email' => 'hkt2@hktsolution.vn',
                'phone' => '02862828282',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
            ],
            [
                'code' => 'HKT3',
                'name' => 'Chi nhánh HKT 3',
                'tax_code' => '0312345673',
                'email' => 'hkt3@hktsolution.vn',
                'phone' => '02862828283',
                'address' => 'Hải Châu, Đà Nẵng',
            ],
            [
                'code' => 'HKT4',
                'name' => 'Chi nhánh HKT 4',
                'tax_code' => '0312345674',
                'email' => 'hkt4@hktsolution.vn',
                'phone' => '02862828284',
                'address' => 'Hoàn Kiếm, Hà Nội',
            ],
        ];

        foreach ($branches as $b) {
            SystemBranch::updateOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'tax_code' => $b['tax_code'],
                    'email' => $b['email'],
                    'phone' => $b['phone'],
                    'address' => $b['address'],
                    'accounting_month' => 11,
                    'accounting_year' => 2024,
                    'is_active' => true,
                ]
            );
        }
    }
}
