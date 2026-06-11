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
        Schema::create('standard_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_class_id')->constrained('room_classes')->onDelete('cascade');
            $table->foreignId('room_form_id')->constrained('room_forms')->onDelete('cascade');
            $table->decimal('room_price', 15, 2)->default(0); // Giá phòng
            $table->decimal('extra_bed_price', 15, 2)->default(0); // Giá thêm giường
            $table->timestamps();

            $table->unique(['room_class_id', 'room_form_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standard_rates');
    }
};
