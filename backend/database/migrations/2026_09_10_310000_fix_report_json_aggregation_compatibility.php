<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $housekeepingMigration = require database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php');

        DB::unprepared('DROP PROCEDURE IF EXISTS `rpt_laundry_invoices`');
        DB::unprepared($housekeepingMigration->procedureSql('rpt_laundry_invoices', 'LA'));
    }

    public function down(): void
    {
        // Giữ procedure tương thích khi rollback migration hiệu chỉnh.
    }
};
