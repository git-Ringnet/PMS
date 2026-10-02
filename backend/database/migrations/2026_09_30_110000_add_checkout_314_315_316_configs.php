<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'name' => 'RoleUserAllowTransBillPastDateToAnotherBK',
                'value' => '',
                'description' => 'Danh sách mã role được chuyển bill ngày cũ sang Booking khác, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
            ],
            [
                'name' => 'RoleUserPostBillCheckedOutRoom',
                'value' => '',
                'description' => 'Danh sách mã role được post bill vào Booking/phòng đã checkout, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
            ],
            [
                'name' => 'RoleUserAdjustRoomRate',
                'value' => '',
                'description' => 'Danh sách mã role được điều chỉnh giá phòng tại Checkout, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
            ],
        ];

        foreach ($configs as $config) {
            if (!DB::table('hotel_configs')->where('name', $config['name'])->exists()) {
                DB::table('hotel_configs')->insert([
                    ...$config,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve branch-specific Checkout configuration during rollback.
    }
};
