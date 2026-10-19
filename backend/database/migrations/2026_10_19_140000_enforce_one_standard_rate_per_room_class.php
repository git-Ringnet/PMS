<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('standard_rates')->select('room_class_id')
            ->groupBy('room_class_id')->havingRaw('COUNT(*) > 1')->pluck('room_class_id');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Giá phòng chuẩn trùng room_class_id: '.$duplicates->implode(', ').'. Cần đối chiếu dữ liệu trước khi chạy lại migration; không tự động xóa giá.');
        }
        Schema::table('standard_rates', fn (Blueprint $table) => $table->unique('room_class_id', 'standard_rates_room_class_unique'));
    }

    public function down(): void
    {
        Schema::table('standard_rates', fn (Blueprint $table) => $table->dropUnique('standard_rates_room_class_unique'));
    }
};
