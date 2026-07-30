<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng room_night_bills (tương đương SP3004 — PhieuLuuTru)
     * Lưu metadata tiền phòng gắn với từng bill trong service_bills (SP3000).
     *
     * is_room_night:
     *   1 = Tiền phòng (Room Charge)
     *   0 = Phụ thu tiền phòng (Room Surcharge)
     */
    public function up(): void
    {
        Schema::create('room_night_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('bill_id')->primary(); // FK → service_bills.Ma

            $table->unsignedSmallInteger('adult')->default(1);
            $table->unsignedSmallInteger('child')->default(0);

            // 1 = Tiền phòng (IsRoomNight=1), 0 = Phụ thu (IsRoomNight=0)
            $table->unsignedTinyInteger('is_room_night')->default(1);

            $table->decimal('breakfast_amount', 15, 2)->default(0); // Tiền ăn sáng
            $table->decimal('extrabed_amount', 15, 2)->default(0);  // Tiền extra bed

            $table->date('date');
            $table->string('room', 20)->nullable();
            $table->unsignedInteger('room_type_id')->nullable();
            $table->unsignedInteger('room_kind_id')->nullable();

            $table->unsignedSmallInteger('breakfast')->default(0);   // Số suất ăn sáng
            $table->unsignedSmallInteger('extra_bed')->default(0);   // Số extra bed

            $table->string('rate_code', 50)->nullable();
            $table->decimal('rate', 15, 2)->default(0);              // Giá phòng thực

            $table->timestamps();

            $table->index('date');
            $table->index('room');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_night_bills');
    }
};
