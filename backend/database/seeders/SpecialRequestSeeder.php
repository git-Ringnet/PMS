<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecialRequestSeeder extends Seeder
{
    /** Seed 3 TypeSpecial theo nghiep vu dong 23. */
    public function run(): void
    {
        $requests = [
            [
                'code' => 'HM',
                'name' => 'Phòng tuần trăng mật',
                'icon' => 'heart',
                'description' => 'Yêu cầu phòng tuần trăng mật',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'BD',
                'name' => 'Sinh nhật',
                'icon' => 'cake',
                'description' => 'Yêu cầu chuẩn bị sinh nhật',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'BC',
                'name' => 'Nôi em bé',
                'icon' => 'baby-cot',
                'description' => 'Yêu cầu chuẩn bị nôi em bé',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        $activeCodes = array_column($requests, 'code');

        // Không xóa bản ghi ngoài nghiệp vụ để bảo toàn lịch sử liên kết.
        DB::table('special_requests')
            ->whereNotIn('code', $activeCodes)
            ->update(['is_active' => false, 'updated_at' => now()]);

        foreach ($requests as $item) {
            DB::table('special_requests')->updateOrInsert(
                ['code' => $item['code']],
                array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('SpecialRequestSeeder: da seed 3 TypeSpecial.');
    }
}
