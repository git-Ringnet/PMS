<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_statuses', function (Blueprint $table) {
            // bk_definite = 4 → khi hủy booking, hệ thống chuyển registration_status sang dòng có bk_definite = 4
            // null hoặc giá trị khác = không có hành vi tự động
            $table->unsignedTinyInteger('bk_definite')->nullable()->after('is_availability')
                ->comment('4 = trạng thái tự chuyển khi hủy booking. null = không tự chuyển.');
        });
    }

    public function down(): void
    {
        Schema::table('registration_statuses', function (Blueprint $table) {
            $table->dropColumn('bk_definite');
        });
    }
};
