<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CancelReasonSeeder extends Seeder
{
    /**
     * Seed danh mục lý do hủy phòng / hủy booking (cancel_reasons — SP1334)
     * Dùng cho Epic 9: Hủy phòng / Hủy đăng ký — lý do ghi vào booking_cancel_logs
     */
    public function run(): void
    {
        $reasons = [
            // ---- PHÍA KHÁCH ----
            [
                'name'        => 'Khách tự hủy',
                'description' => 'Khách chủ động liên hệ hủy đặt phòng',
                'is_active'   => true,
            ],
            [
                'name'        => 'Khách thay đổi lịch',
                'description' => 'Khách hủy do thay đổi lịch trình / kế hoạch',
                'is_active'   => true,
            ],
            [
                'name'        => 'Khách tìm được chỗ khác',
                'description' => 'Khách hủy vì đã đặt chỗ ở nơi khác',
                'is_active'   => true,
            ],
            [
                'name'        => 'Khách không liên lạc được (No Show)',
                'description' => 'Khách không đến và không thông báo trước',
                'is_active'   => true,
            ],
            [
                'name'        => 'Khách hủy do giá không phù hợp',
                'description' => 'Khách hủy vì mức giá quá cao so với kỳ vọng',
                'is_active'   => true,
            ],

            // ---- PHÍA CÔNG TY / LỮHÀNH ----
            [
                'name'        => 'Đại lý hủy đặt phòng',
                'description' => 'Công ty / đại lý du lịch hủy toàn bộ đặt phòng',
                'is_active'   => true,
            ],
            [
                'name'        => 'Hủy do hết hạn deposit',
                'description' => 'Booking bị hủy tự động vì không nhận được đặt cọc đúng hạn',
                'is_active'   => true,
            ],

            // ---- PHÍA KHÁCH SẠN ----
            [
                'name'        => 'Phòng không sẵn sàng (OOO/OOS)',
                'description' => 'Khách sạn hủy do phòng đột ngột bị OOO/OOS (sự cố kỹ thuật, bảo trì)',
                'is_active'   => true,
            ],
            [
                'name'        => 'Overbooking — điều chuyển khách',
                'description' => 'Hủy do khách sạn overbook, chuyển khách sang nơi khác',
                'is_active'   => true,
            ],
            [
                'name'        => 'Lỗi nhập liệu / Booking trùng',
                'description' => 'Hủy do nhập sai thông tin hoặc booking bị tạo trùng',
                'is_active'   => true,
            ],

            // ---- KHÁC ----
            [
                'name'        => 'Lý do khác',
                'description' => 'Lý do hủy khác không thuộc danh mục trên (ghi chú thêm khi hủy)',
                'is_active'   => true,
            ],
        ];

        foreach ($reasons as $item) {
            DB::table('cancel_reasons')->updateOrInsert(
                ['name' => $item['name']],
                array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ CancelReasonSeeder: Đã seed ' . count($reasons) . ' lý do hủy phòng.');
    }
}
