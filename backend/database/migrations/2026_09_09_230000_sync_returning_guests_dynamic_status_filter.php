<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'mysql') {
            return;
        }

        (require database_path('migrations/2026_09_09_220000_create_returning_guests_report.php'))->up();
    }

    public function down(): void
    {
        // Giữ nguyên procedure của báo cáo khách quay lại.
    }
};
