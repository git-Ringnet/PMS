<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng residence_declarations (SP8007 — Danh sách khách đã khai báo lưu trú)
     * Khác SP8007 (PK chỉ theo CustomerId): đánh dấu theo từng lượt ở (khách + phòng thuê)
     * vì guests là hồ sơ dùng chung, khách quay lại lần sau vẫn phải khai báo lại.
     */
    public function up(): void
    {
        if (!Schema::hasTable('residence_declarations')) {
            Schema::create('residence_declarations', function (Blueprint $table) {
                $table->id();
                $table->string('guest_id', 50);                     // guests.id hoặc booking_children.id (CustomerId)
                $table->string('guest_kind', 10)->default('guest'); // guest = người lớn, child = trẻ em
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->string('booking_room_id', 50);
                $table->dateTime('declare_date');                   // DeclareDate
                $table->string('declarer', 50);                     // Declarer
                $table->integer('status')->nullable()->default(1);  // Status: 1 = đã khai báo
                $table->timestamps();

                $table->unique(['guest_kind', 'guest_id', 'booking_room_id'], 'residence_declarations_stay_unique');
                $table->index('booking_room_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residence_declarations');
    }
};
