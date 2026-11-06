<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $config = [
            'name' => 'CreateCompanyRequiredField',
            'value' => '',
            'description' => 'Các trường bắt buộc bổ sung khi thêm/sửa công ty, phân cách bằng dấu phẩy: address, tax_code, phone, email, customer_source_id, market_id, max_debt, bank_account, booker_id, sales_person_id, branch_id.',
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
        DB::table('hotel_configs')->where('name', 'CreateCompanyRequiredField')->delete();
    }
};
