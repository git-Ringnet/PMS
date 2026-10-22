<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $config = [
            'name' => 'CompanyDefault',
            'value' => '',
            'description' => 'ID công ty mặc định khi tạo Booking nhanh và Nhận phòng nhanh',
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!DB::table('hotel_configs')->where('name', $config['name'])->exists()) {
            DB::table('hotel_configs')->insert($config);
        }
    }

    public function down(): void
    {
        DB::table('hotel_configs')->where('name', 'CompanyDefault')->delete();
    }
};
