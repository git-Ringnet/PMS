<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $config = [
            'name' => 'IsCopyAllBooking',
            'value' => '1',
            'description' => 'Cho phép nhân bản cả phòng khi copy booking (1: Nhân bản cả thông tin booking và các phòng; 0: Chỉ nhân bản thông tin booking header, không copy phòng)',
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!DB::table('hotel_configs')->where('name', $config['name'])->exists()) {
            DB::table('hotel_configs')->insert($config);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('hotel_configs')->where('name', 'IsCopyAllBooking')->delete();
    }
};
