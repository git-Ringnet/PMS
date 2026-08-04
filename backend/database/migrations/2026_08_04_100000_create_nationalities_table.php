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
        Schema::create('nationalities', function (Blueprint $table) {
            $table->id();

            // Columns from SP8020 (Quốc tịch chính của PMS)
            $table->string('nationality_id', 10)->nullable();
            $table->string('nationality_id2', 50)->nullable();
            $table->integer('nationality_id_number')->nullable();
            $table->string('nationality_name', 255)->nullable();
            $table->string('nationality_name_en', 255)->nullable();
            $table->bigInteger('nationality_id_uid')->nullable();
            $table->bigInteger('nationality_id_shift')->nullable();
            $table->string('nationality_code', 255)->nullable();
            $table->integer('continent_code')->nullable();

            // Columns from asmcountry (Khai báo tạm trú Công an)
            $table->integer('asm_id')->nullable();
            $table->string('asm_code', 20)->nullable();
            $table->string('asm_name', 255)->nullable();
            $table->string('asm_description', 255)->nullable();

            $table->timestamps();

            // Index để map dữ liệu cũ nhanh chóng
            $table->index('nationality_id');
            $table->index('asm_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nationalities');
    }
};
