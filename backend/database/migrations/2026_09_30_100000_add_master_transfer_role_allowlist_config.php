<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'name' => 'Bill_TransBillPastDateToAnotherBK',
                'value' => '0',
                'description' => 'Cho phép chuyển bill ngày cũ sang Booking khác (0: không cho phép, 1: cho phép).',
            ],
            [
                'name' => 'RoleUserAllowTransBillPastDateToAnotherBK',
                'value' => '',
                'description' => 'Danh sách mã role được chuyển bill ngày cũ sang Booking khác, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
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
        // Preserve branch-specific role configuration during rollback.
    }
};
