<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hotel_configs')->insertOrIgnore([
            'name' => 'SystemThemeTopbarColor',
            'value' => '#006bdb',
            'description' => 'Màu nền thanh hệ thống dùng chung cho toàn bộ người dùng.',
            'is_visible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('hotel_configs')
            ->where('name', 'SystemThemeTopbarColor')
            ->where('value', '#006bdb')
            ->delete();
    }
};
