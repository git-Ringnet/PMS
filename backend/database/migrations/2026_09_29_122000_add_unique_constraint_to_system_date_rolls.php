<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Kiểm tra an toàn: nếu phát hiện trùng lặp ngày nghiệp vụ, fail an toàn và báo lỗi, không tự ý xóa dữ liệu.
        $duplicates = DB::select("
            SELECT DATE(system_date) as date_val, COUNT(*) as cnt 
            FROM system_date_rolls 
            GROUP BY DATE(system_date) 
            HAVING cnt > 1
        ");

        if (!empty($duplicates)) {
            $details = json_encode($duplicates);
            throw new \RuntimeException(
                "Không thể tạo unique constraint: Phát hiện dữ liệu trùng lặp ngày trong system_date_rolls: {$details}. Vui lòng rà soát thủ công trước khi áp dụng migration."
            );
        }

        // 2. Thêm Unique Constraint cho system_date nếu chưa tồn tại
        $hasIndex = false;
        try {
            $indexes = Schema::getIndexes('system_date_rolls');
            foreach ($indexes as $idx) {
                if (($idx['name'] ?? '') === 'uniq_system_date_rolls_system_date') {
                    $hasIndex = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        if (!$hasIndex) {
            Schema::table('system_date_rolls', function (Blueprint $table) {
                $table->unique('system_date', 'uniq_system_date_rolls_system_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_date_rolls', function (Blueprint $table) {
            $table->dropUnique('uniq_system_date_rolls_system_date');
        });
    }
};
