<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch sử thao tác: "Ngày thao tác" là ngày hệ thống (ngày nghiệp vụ system_date_rolls) lúc thao tác,
 * không phải ngày giờ máy. Bộ lọc thời gian lọc theo cột này.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs') || Schema::hasColumn('activity_logs', 'system_date')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->date('system_date')->nullable()->after('log_version');
            $table->index('system_date');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs') || !Schema::hasColumn('activity_logs', 'system_date')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['system_date']);
            $table->dropColumn('system_date');
        });
    }
};
