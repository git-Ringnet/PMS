<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('hotel_configs')->where('name', 'SearchHouseKeeping_AllowHKCorrectBillFO')->exists()) {
            DB::table('hotel_configs')->insert([
                'name' => 'SearchHouseKeeping_AllowHKCorrectBillFO',
                'value' => '0',
                'description' => 'Cho phép module Buồng phòng xóa (hủy) bill do Lễ tân post (0: Không cho phép, 1: Cho phép).',
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Giữ nguyên cấu hình khách sạn khi rollback.
    }
};
