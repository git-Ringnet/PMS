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
        // 1. SP8015 - Danh xưng (guest_titles)
        Schema::create('guest_titles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->unsignedTinyInteger('gender')->default(0); // 0: Nam, 1: Nữ, 2: Khác
            $table->boolean('is_adult')->default(true);
            $table->boolean('is_infant')->default(false);
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. SP8017 - Cửa khẩu (border_gates)
        Schema::create('border_gates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('gate_type', 50)->nullable(); // air, land, sea
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. SP8019 - Mục đích lưu trú / nhập cảnh (entry_purposes)
        Schema::create('entry_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. SP8042 - Loại khách (guest_types)
        Schema::create('guest_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. SP8055 - Loại giấy tờ (id_types)
        Schema::create('id_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_types');
        Schema::dropIfExists('guest_types');
        Schema::dropIfExists('entry_purposes');
        Schema::dropIfExists('border_gates');
        Schema::dropIfExists('guest_titles');
    }
};
