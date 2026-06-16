<?php

namespace Database\Seeders;

use App\Models\InfoBusiness;
use App\Models\SystemBranch;
use Illuminate\Database\Seeder;

class InfoBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = SystemBranch::where('code', 'HKT1')->first();

        InfoBusiness::updateOrCreate(
            ['id' => 1],
            [
                'company_name' => 'HKT Solutions 111',
                'bank_name' => 'HKT Solutions',
                'chairman' => 'Nguyễn Văn A',
                'phone' => '0868 552 526',
                'email' => 'info@hktsolution.vn',
                'director' => 'Lê Văn C',
                'address' => 'Lô 50 đường, 19 Tháng 5, Vĩnh Hiệp, Nha Trang, Khánh Hòa',
                'system_branch_id' => $branch ? $branch->id : null,
                'chief_accountant' => 'Phạm Thị D',
                'logo_url' => null,
            ]
        );
    }
}
