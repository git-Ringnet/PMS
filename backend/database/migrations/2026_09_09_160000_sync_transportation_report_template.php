<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'mysql') {
            return;
        }

        (require database_path('migrations/2026_09_09_150000_create_transportation_report.php'))->up();
    }

    public function down(): void
    {
        // Giữ nguyên report; migration này chỉ đồng bộ procedure và template.
    }
};
