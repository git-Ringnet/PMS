<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo 3 bảng danh mục/hỗ trợ nghiệp vụ Đặt phòng:
     *   1. special_requests     (SP1325) — Danh mục yêu cầu đặc biệt
     *   2. cancel_reasons       (SP1334) — Danh mục lý do hủy
     *   3. room_do_not_move_locks (SP8022) — Khóa chuyển phòng (Do Not Move)
     */
    public function up(): void
    {
        // =============================================
        // 1. special_requests (SP1325 — YeuCauDacBiet)
        // Danh mục master các loại yêu cầu đặc biệt
        // VD: honey_moon, birthday, baby_cot, high_floor...
        // =============================================
        Schema::create('special_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();       // Mã định danh: 'honey_moon', 'birthday'...
            $table->string('name', 150);                // Tên hiển thị: 'Phòng tuần trăng mật'
            $table->string('icon', 100)->nullable();    // Icon class/name để hiển thị trên Room Map
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0); // Thứ tự hiển thị
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // 2. cancel_reasons (SP1334 — LyDoHuyPhong)
        // Danh mục lý do hủy phòng / hủy booking
        // =============================================
        Schema::create('cancel_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);               // Tên lý do hủy
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // 3. room_do_not_move_locks (SP8022 — DoNotMove)
        // Khóa không cho đổi/chuyển số phòng đã gán
        // Một booking_room có thể có nhiều lần lock/unlock
        // Trạng thái active: unlocked_at IS NULL
        // =============================================
        Schema::create('room_do_not_move_locks', function (Blueprint $table) {
            $table->id();
            $table->string('booking_room_id', 50);
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('locked_by_user_id');   // ID user đã khóa
            $table->string('locked_by_username')->nullable();   // Username người khóa (lưu lại cho display)
            $table->dateTime('locked_at');                     // Thời điểm khóa
            $table->unsignedBigInteger('unlocked_by_user_id')->nullable(); // ID user đã mở
            $table->string('unlocked_by_username')->nullable(); // Username người mở
            $table->dateTime('unlocked_at')->nullable();        // Thời điểm mở (NULL = đang khóa)
            $table->string('note', 255)->nullable();            // Ghi chú lý do khóa
            $table->timestamps();

            $table->index('booking_room_id');
            $table->index('unlocked_at'); // Query: bản ghi nào đang active (unlocked_at IS NULL)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_do_not_move_locks');
        Schema::dropIfExists('cancel_reasons');
        Schema::dropIfExists('special_requests');
    }
};
