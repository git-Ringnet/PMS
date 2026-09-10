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

        $migration = require database_path('migrations/2026_09_10_260000_create_company_debt_report.php');
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt');
        DB::unprepared($migration->procedureSql());
    }

    public function down(): void
    {
        // Giữ procedure tương thích khi rollback migration hiệu chỉnh.
    }
};
