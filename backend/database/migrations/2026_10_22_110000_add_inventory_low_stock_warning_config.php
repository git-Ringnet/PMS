<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('hotel_configs')->where('name', 'Inventory_LowStockWarningQty')->exists()) {
            DB::table('hotel_configs')->insert([
                'name' => 'Inventory_LowStockWarningQty',
                'value' => '50',
                'description' => 'Định mức cảnh báo tồn kho: sản phẩm có tồn cuối nhỏ hơn số này được đánh dấu cảnh báo ở màn Tồn kho (mặc định 50).',
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
