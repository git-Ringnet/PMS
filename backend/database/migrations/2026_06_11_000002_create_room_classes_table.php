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
        Schema::create('room_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên loại phòng
            $table->string('code')->unique(); // Tên viết tắt
            $table->string('color')->default('#ffffff'); // Màu sắc
            $table->boolean('is_active')->default(true); // Có sử dụng
            $table->foreignId('room_class_group_id')
                ->nullable()
                ->constrained('room_class_groups')
                ->nullOnDelete(); // Nhóm loại phòng
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_classes');
    }
};
