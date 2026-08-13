<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HkStaffSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['name' => 'Nguyễn Thị Lan', 'sort_order' => 1],
            ['name' => 'Trần Thị Mai', 'sort_order' => 2],
            ['name' => 'Lê Thị Hoa', 'sort_order' => 3],
            ['name' => 'Phạm Thị Thu', 'sort_order' => 4],
            ['name' => 'Vũ Thị Ngọc', 'sort_order' => 5],
            ['name' => 'Đặng Thị Hương', 'sort_order' => 6],
            ['name' => 'Bùi Thị Linh', 'sort_order' => 7],
            ['name' => 'Hoàng Thị Yến', 'sort_order' => 8],
            ['name' => 'Ngô Thị Thanh', 'sort_order' => 9],
            ['name' => 'Đinh Văn Minh', 'sort_order' => 10],
            ['name' => 'Trương Văn Nam', 'sort_order' => 11],
            ['name' => 'Phan Thị Quỳnh', 'sort_order' => 12],
        ];

        foreach ($staff as $s) {
            DB::table('hk_staff')->updateOrInsert(
                ['name' => $s['name']],
                [
                    'is_active' => true,
                    'is_hidden' => false,
                    'sort_order' => $s['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
