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
        Schema::create('booking_statuses', function (Blueprint $table) {
            // Khóa chính là số nguyên tương ứng các hằng số: 0, 1, 2, 3, 4, 100
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_statuses');
    }
};
