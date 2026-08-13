<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 6. Bảng ký hiệu tình trạng phòng HK
        // group: 'hk' | 'booking' | 'extra'
        Schema::create('hk_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);          // hk | booking | extra
            $table->string('status_key', 50);     // occupied_dirty | checkin | ep ...
            $table->string('code', 20);           // OD | CI | EA ...
            $table->string('label', 100);         // Nhãn hiển thị tiếng Việt
            $table->string('color', 30)->nullable(); // mã màu hex
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group', 'status_key']);
        });

        // 7. Bảng cấu hình cột mẫu in worksheet
        // template: 'worksheet' | 'supervisor'
        // is_fixed: cột dữ liệu cố định không xóa (Room No, Type, Status)
        Schema::create('hk_print_cols', function (Blueprint $table) {
            $table->id();
            $table->string('template', 20);       // worksheet | supervisor
            $table->string('label', 100);
            $table->string('width', 20)->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hk_print_cols');
        Schema::dropIfExists('hk_symbols');
    }
};
