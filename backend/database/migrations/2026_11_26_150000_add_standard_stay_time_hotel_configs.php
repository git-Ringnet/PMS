<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Giờ nhận phòng / trả phòng tiêu chuẩn: thông số ở Định nghĩa khách sạn › Cấu Hình
 * (giờ mặc định cho phòng mới, phục vụ tính tiền lưu trú). Không ghi đè giá trị đã cấu hình.
 */
return new class extends Migration
{
    private const CONFIGS = [
        'StandardCheckInTime' => ['14:00:00', 'Giờ nhận phòng tiêu chuẩn (HH:mm), dùng làm giờ đến mặc định cho phòng mới. Không đổi giờ của phòng đã tạo.'],
        'StandardCheckOutTime' => ['12:00:00', 'Giờ trả phòng tiêu chuẩn (HH:mm), dùng làm giờ đi mặc định cho phòng mới. Không đổi giờ của phòng đã tạo.'],
    ];

    public function up(): void
    {
        foreach (self::CONFIGS as $name => [$value, $description]) {
            $existing = DB::table('hotel_configs')->where('name', $name)->first();
            if ($existing) {
                if (empty($existing->description)) {
                    DB::table('hotel_configs')->where('name', $name)->update(['description' => $description, 'updated_at' => now()]);
                }
                continue;
            }
            DB::table('hotel_configs')->insert([
                'name' => $name, 'value' => $value, 'description' => $description,
                'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('hotel_configs')->whereIn('name', array_keys(self::CONFIGS))->delete();
    }
};
