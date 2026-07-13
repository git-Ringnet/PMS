<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo bảng booking_cancel_logs — gộp 2 bảng:
     *   - SP8052 (HuyPhongDangKy)   — log hủy từng phòng
     *   - SP8053 (HuyDangKy)        — log hủy cả booking
     * Phân biệt bằng cột cancel_type: 'room' | 'booking'
     */
    public function up(): void
    {
        Schema::create('booking_cancel_logs', function (Blueprint $table) {
            $table->id();

            // =========================================
            // PHÂN LOẠI HỦY
            // =========================================
            // 'room'    = Hủy 1 phòng cụ thể trong booking (SP8052)
            // 'booking' = Hủy toàn bộ booking (SP8053)
            $table->enum('cancel_type', ['room', 'booking'])->default('room');

            // =========================================
            // THAM CHIẾU ĐỐI TƯỢNG BỊ HỦY
            // =========================================
            // Một trong hai sẽ có giá trị tùy cancel_type:
            $table->unsignedBigInteger('booking_id')->nullable();     // FK bookings (dùng khi cancel_type='booking')
            $table->unsignedBigInteger('booking_room_id')->nullable(); // FK booking_rooms (dùng khi cancel_type='room')

            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->nullOnDelete();

            // =========================================
            // LÝ DO HỦY
            // =========================================
            $table->foreignId('cancel_reason_id')
                ->nullable()
                ->constrained('cancel_reasons')
                ->nullOnDelete();
            $table->text('note')->nullable(); // Ghi chú tự do thêm vào

            // =========================================
            // NGƯỜI HỦY & THỜI ĐIỂM
            // =========================================
            $table->unsignedBigInteger('cancelled_by_user_id');
            $table->string('cancelled_by_username')->nullable(); // Lưu lại tên để không phụ thuộc FK users
            $table->dateTime('cancelled_at');

            $table->timestamps();

            // =========================================
            // INDEXES
            // =========================================
            $table->index('booking_id');
            $table->index('booking_room_id');
            $table->index('cancel_type');
            $table->index('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_cancel_logs');
    }
};
