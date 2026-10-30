<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dòng 282 (note 23/10): một số phòng đã lưu chữ gợi ý "Vui lòng chọn giá phòng" làm mã giá,
 * khiến lưu thông tin khách / phòng báo "The selected rate code is invalid". Mã giá không bắt buộc
 * nên chuyển về NULL (chưa chọn mã giá).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_rooms')
            ->where('rate_code', 'Vui lòng chọn giá phòng')
            ->update(['rate_code' => null]);
    }

    public function down(): void
    {
        // Không khôi phục chữ gợi ý vào dữ liệu.
    }
};
