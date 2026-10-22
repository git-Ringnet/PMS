<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'name' => 'DisablePrintBillAfterPayment',
                'value' => '0',
                'description' => 'Tự động in hóa đơn (phiếu thu) sau khi thanh toán thành công tại Hóa đơn. 0: không in, 1: tự động in.',
            ],
            [
                'name' => 'RoleUserReCheckout',
                // Mặc định cho các role quản lý để sau deploy vẫn có người khôi phục được checkout.
                'value' => 'super_admin,branch_admin,fo_manager',
                'description' => 'Danh sách mã role được khôi phục checkout (re checkout) Master booking và phòng đã checkout, phân cách bằng dấu phẩy. Để trống sẽ từ chối.',
            ],
        ];

        foreach ($configs as $config) {
            if (!DB::table('hotel_configs')->where('name', $config['name'])->exists()) {
                DB::table('hotel_configs')->insert([
                    ...$config,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve branch-specific Checkout configuration during rollback.
    }
};
