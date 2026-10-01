<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('hotel_configs')->where('name', 'RuleUserModifyRateBillService')->exists()) {
            return;
        }

        DB::table('hotel_configs')->insert([
            'name' => 'RuleUserModifyRateBillService',
            'value' => '',
            'description' => 'Danh sách mã role được điều chỉnh giá bill dịch vụ tại Checkout, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
            'is_visible' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve branch-specific role configuration during rollback.
    }
};
