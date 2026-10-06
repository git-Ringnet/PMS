<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('hotel_configs')->where('name', 'CheckAuthorization')->exists()) {
            return;
        }

        DB::table('hotel_configs')->insert([
            'name' => 'CheckAuthorization',
            'value' => '0',
            'description' => 'Xác thực mật khẩu người dùng khi thực hiện thao tác xóa cọc, xóa booking, xóa thanh toán, xóa dịch vụ, thanh toán (0: Không xác thực, khác 0: Xác thực)',
            'is_visible' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('hotel_configs')->where('name', 'CheckAuthorization')->delete();
    }
};
