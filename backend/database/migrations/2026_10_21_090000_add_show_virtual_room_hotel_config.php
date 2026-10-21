<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $config = [
            'name' => 'ShowVirtualRoom',
            'value' => '1',
            'description' => 'Kế hoạch phòng: hiển thị phòng chưa gán số phòng (0: ẩn, 1: hiển thị)',
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
        DB::table('hotel_configs')->where('name', 'ShowVirtualRoom')->delete();
    }
};
