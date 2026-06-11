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
        Schema::create('customer_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Mã nguồn khách (OTA, FOC, GIT...)
            $table->string('name'); // Tên nguồn khách
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_sources');
    }
};
