<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Xóa cột room_allocations (JSON) khỏi bảng bookings.
     * Dữ liệu đã được migrate sang bảng booking_rooms riêng.
     * Cột này không còn cần thiết sau khi tạo booking_rooms.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('room_allocations');
        });
    }

    /**
     * Reverse the migrations.
     * Khôi phục lại cột room_allocations nếu cần rollback.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('room_allocations')->nullable()->after('shuttle_info');
        });
    }
};
