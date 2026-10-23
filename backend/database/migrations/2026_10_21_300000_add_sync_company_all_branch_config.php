<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('hotel_configs')->where('name', 'SyncCompanyAllBranch')->exists()) {
            DB::table('hotel_configs')->insert([
                'name' => 'SyncCompanyAllBranch',
                'value' => '0',
                'description' => 'Đồng bộ công ty, thị trường, nguồn khách cho tất cả chi nhánh trong chuỗi (0: Không, mỗi chi nhánh lưu riêng; 1: Thêm/sửa/xóa ở một chi nhánh thì các chi nhánh khác tự cập nhật theo, không đồng bộ người bán).',
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
