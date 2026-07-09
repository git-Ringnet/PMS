<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecialRequestSeeder extends Seeder
{
    /**
     * Seed danh mục yêu cầu đặc biệt (special_requests — SP1325)
     * Dùng cho Epic 15: Special Request + icon Room Map
     */
    public function run(): void
    {
        $requests = [
            [
                'code'        => 'honey_moon',
                'name'        => 'Phòng tuần trăng mật',
                'icon'        => 'heart',          // Icon trái tim — hiển thị trên Room Map khi sắp đến / inhouse
                'description' => 'Trang trí phòng theo chủ đề tuần trăng mật (hoa, rượu champagne...)',
                'sort_order'  => 1,
                'is_active'   => true,
            ],
            [
                'code'        => 'birthday',
                'name'        => 'Sinh nhật',
                'icon'        => 'cake',           // Icon bánh kem — hiển thị trên Room Map
                'description' => 'Chuẩn bị bánh sinh nhật và trang trí phòng theo yêu cầu',
                'sort_order'  => 2,
                'is_active'   => true,
            ],
            [
                'code'        => 'baby_cot',
                'name'        => 'Nôi em bé',
                'icon'        => 'baby-cot',       // Tính vào số lượng baby cot hiển thị trên Room AV
                'description' => 'Chuẩn bị nôi/cũi cho em bé trong phòng',
                'sort_order'  => 3,
                'is_active'   => true,
            ],
            [
                'code'        => 'high_floor',
                'name'        => 'Tầng cao',
                'icon'        => 'building',
                'description' => 'Yêu cầu phòng ở tầng cao (view đẹp)',
                'sort_order'  => 4,
                'is_active'   => true,
            ],
            [
                'code'        => 'low_floor',
                'name'        => 'Tầng thấp',
                'icon'        => 'stairs-down',
                'description' => 'Yêu cầu phòng ở tầng thấp (tiện di chuyển)',
                'sort_order'  => 5,
                'is_active'   => true,
            ],
            [
                'code'        => 'quiet_room',
                'name'        => 'Phòng yên tĩnh',
                'icon'        => 'volume-off',
                'description' => 'Yêu cầu phòng ở khu vực yên tĩnh, tránh ồn',
                'sort_order'  => 6,
                'is_active'   => true,
            ],
            [
                'code'        => 'connecting_room',
                'name'        => 'Phòng thông nhau',
                'icon'        => 'door-open',
                'description' => 'Yêu cầu phòng có cửa thông sang phòng kế bên (cho gia đình)',
                'sort_order'  => 7,
                'is_active'   => true,
            ],
            [
                'code'        => 'early_checkin',
                'name'        => 'Check-in sớm',
                'icon'        => 'clock-early',
                'description' => 'Khách yêu cầu nhận phòng trước giờ quy định',
                'sort_order'  => 8,
                'is_active'   => true,
            ],
            [
                'code'        => 'late_checkout',
                'name'        => 'Late Check-out',
                'icon'        => 'clock-late',
                'description' => 'Khách yêu cầu trả phòng muộn hơn giờ quy định',
                'sort_order'  => 9,
                'is_active'   => true,
            ],
            [
                'code'        => 'extra_pillow',
                'name'        => 'Thêm gối',
                'icon'        => 'pillow',
                'description' => 'Yêu cầu thêm gối trong phòng',
                'sort_order'  => 10,
                'is_active'   => true,
            ],
            [
                'code'        => 'extra_towel',
                'name'        => 'Thêm khăn tắm',
                'icon'        => 'towel',
                'description' => 'Yêu cầu thêm khăn tắm',
                'sort_order'  => 11,
                'is_active'   => true,
            ],
            [
                'code'        => 'non_smoking',
                'name'        => 'Phòng không hút thuốc',
                'icon'        => 'no-smoking',
                'description' => 'Yêu cầu phòng không hút thuốc lá',
                'sort_order'  => 12,
                'is_active'   => true,
            ],
            [
                'code'        => 'smoking',
                'name'        => 'Phòng hút thuốc',
                'icon'        => 'smoking',
                'description' => 'Yêu cầu phòng có khu vực hút thuốc',
                'sort_order'  => 13,
                'is_active'   => true,
            ],
            [
                'code'        => 'anniversary',
                'name'        => 'Kỷ niệm ngày đặc biệt',
                'icon'        => 'anniversary',
                'description' => 'Trang trí phòng cho kỷ niệm ngày đặc biệt (wedding anniversary...)',
                'sort_order'  => 14,
                'is_active'   => true,
            ],
            [
                'code'        => 'wheelchair',
                'name'        => 'Phòng dành cho người khuyết tật',
                'icon'        => 'wheelchair',
                'description' => 'Yêu cầu phòng có tiện nghi cho người khuyết tật / xe lăn',
                'sort_order'  => 15,
                'is_active'   => true,
            ],
        ];

        foreach ($requests as $item) {
            DB::table('special_requests')->updateOrInsert(
                ['code' => $item['code']],
                array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ SpecialRequestSeeder: Đã seed ' . count($requests) . ' yêu cầu đặc biệt.');
    }
}
