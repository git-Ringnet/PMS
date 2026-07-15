<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo bảng booking_room_special_requests (SP2107 — PhongThueSpecialRequest)
     * Gán các yêu cầu đặc biệt cho từng phòng trong booking.
     * Nhiều-nhiều: booking_rooms <-> special_requests
     */
    public function up(): void
    {
        Schema::create('booking_room_special_requests', function (Blueprint $table) {
            $table->id();
            $table->string('booking_room_id', 50);
            $table->foreign('booking_room_id')->references('id')->on('booking_rooms')->cascadeOnDelete();
            $table->foreignId('special_request_id')
                ->constrained('special_requests')
                ->cascadeOnDelete();
            $table->text('note')->nullable(); // Ghi chú thêm cho yêu cầu này
            $table->timestamps();

            // Không cho gán trùng cùng 1 yêu cầu vào 1 phòng
            $table->unique(['booking_room_id', 'special_request_id'], 'unique_room_special_request');
            $table->index('booking_room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_room_special_requests');
    }
};
