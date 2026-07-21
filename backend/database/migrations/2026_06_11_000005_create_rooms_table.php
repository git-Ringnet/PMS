<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number')->unique(); // PHÒNG (Số phòng)
            $table->foreignId('room_form_id')->constrained('room_forms')->onDelete('cascade'); // DẠNG PHÒNG
            $table->foreignId('room_class_id')->constrained('room_classes')->onDelete('cascade'); // TÊN LOẠI PHÒNG
            $table->integer('max_guests')->default(2); // Khách hàng
            $table->string('floor'); // Tầng
            $table->string('area')->nullable(); // Khu vực
            $table->integer('extra_beds_limit')->default(0); // Thêm giường
            $table->integer('grid_row')->default(0); // Hàng (vị trí sơ đồ)
            $table->integer('grid_column')->default(0); // Cột (vị trí sơ đồ)
            $table->integer('orders')->default(0); // Sắp xếp thứ tự
            $table->string('owner_room')->nullable(); // Phòng chủ sở hữu
            $table->string('linked_room')->nullable(); // Liên kết
            $table->boolean('is_internal')->default(false); // Phòng nội bộ
            $table->string('status')->default('available'); // Trạng thái (available, occupied, dirty, maintenance, etc.)
            $table->text('notes')->nullable(); // Ghi chú
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
