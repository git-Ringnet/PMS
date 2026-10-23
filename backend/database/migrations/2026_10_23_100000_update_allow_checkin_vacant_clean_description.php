<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng 211: AllowCheckinVacantClean chỉ áp dụng cho phòng chờ kiểm tra, phòng bẩn luôn không được nhận phòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hotel_configs')) {
            return;
        }

        DB::table('hotel_configs')->where('name', 'AllowCheckinVacantClean')->update([
            'description' => 'Cho phép nhận phòng khi phòng ở trạng thái chờ kiểm tra (0: không cho phép, 1: cho phép). Phòng bẩn chưa dọn luôn không được nhận phòng',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('hotel_configs')) {
            return;
        }

        DB::table('hotel_configs')->where('name', 'AllowCheckinVacantClean')->update([
            'description' => 'Cho phép nhận phòng khi phòng ở trạng thái chờ kiểm tra/dirty (0: không cho phép, 1: cho phép)',
        ]);
    }
};
