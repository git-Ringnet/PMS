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
            'name' => 'Immigration_ExportExelChangeToRegistered',
            'value' => '0',
            'description' => 'Màn hình khai báo lưu trú: khi xuất Excel mẫu Khánh Hòa có chuyển khách qua danh sách đã khai báo hay không (1: Có - hiển thị switch "Khách đã đăng ký" và lưu khách đã xuất vào danh sách đã khai báo; 0: Không)',
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
        DB::table('hotel_configs')->where('name', 'Immigration_ExportExelChangeToRegistered')->delete();
    }
};
