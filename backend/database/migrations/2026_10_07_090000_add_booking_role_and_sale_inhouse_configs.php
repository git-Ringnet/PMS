<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'name' => 'AllowReserUpdateRate_DeptDateRoomInhouse',
                'value' => '0',
                'description' => 'Cho phép Sale cập nhật giá phòng, ngày đi và giờ đi của phòng Inhouse (0: Không, 1: Có).',
            ],
            [
                'name' => 'RoleUserUpdateCheckoutBooking',
                'value' => '',
                'description' => 'Danh sách mã role được sửa metadata booking đã checkout; cần có quyền fo.booking.edit. Để trống sẽ từ chối.',
            ],
            [
                'name' => 'RoleUserOpenDoNotMove',
                'value' => '',
                'description' => 'Danh sách mã role chính xác được phép mở khóa Do Not Move; cần có quyền fo.booking.edit. Để trống sẽ từ chối role được cấu hình.',
            ],
        ];

        foreach ($configs as $config) {
            if (!DB::table('hotel_configs')->where('name', $config['name'])->exists()) {
                DB::table('hotel_configs')->insert([
                    ...$config,
                    'is_visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve existing hotel configuration during rollback.
    }
};
