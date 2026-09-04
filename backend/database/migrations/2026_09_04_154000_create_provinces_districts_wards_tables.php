<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tạo 3 bảng địa giới hành chính (tương ứng SP8047, SP8048, SP8049)
     * 1. provinces (SP8047 - Tỉnh/Thành phố)
     * 2. districts (SP8048 - Quận/Huyện)
     * 3. wards     (SP8049 - Phường/Xã)
     */
    public function up(): void
    {
        // 1. Tỉnh / Thành phố (SP8047)
        if (!Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->nullable()->index();
                $table->string('name', 100)->index();
                $table->string('type', 50)->nullable(); // Tỉnh, Thành phố Trung ương
                $table->integer('order_index')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Quận / Huyện (SP8048)
        if (!Schema::hasTable('districts')) {
            Schema::create('districts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->nullable()->index();
                $table->string('name', 100)->index();
                $table->string('type', 50)->nullable(); // Quận, Huyện, Thị xã, Thành phố
                $table->string('province_code', 20)->nullable()->index();
                $table->string('province_name', 100)->nullable()->index();
                $table->integer('order_index')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Phường / Xã (SP8049)
        if (!Schema::hasTable('wards')) {
            Schema::create('wards', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->nullable()->index();
                $table->string('name', 100)->index();
                $table->string('type', 50)->nullable(); // Phường, Xã, Thị trấn
                $table->string('district_code', 20)->nullable()->index();
                $table->string('district_name', 100)->nullable()->index();
                $table->string('province_name', 100)->nullable()->index();
                $table->integer('order_index')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
