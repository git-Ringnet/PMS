<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Re-applies 2026_09_24_100000 on databases that already ran it: adds the
 * "Khu vực" filter (p_area, like @Area of legacy sp_099) and re-syncs the
 * template whose stored copy predates the RM/EB/ER revenue-detail columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require database_path('migrations/2026_09_24_100000_create_room_forecast_report.php'))->up();
    }

    public function down(): void
    {
        // Keep the report configuration recoverable; no destructive rollback is required.
    }
};
