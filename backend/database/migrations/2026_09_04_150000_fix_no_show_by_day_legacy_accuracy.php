<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $reportMigration = require database_path('migrations/2026_09_04_140000_create_no_show_by_day_report.php');
        $reportMigration->up();
    }

    public function down(): void
    {
        // Migration tương thích: không khôi phục lại cấu hình sai đã triển khai trước đó.
    }
};
