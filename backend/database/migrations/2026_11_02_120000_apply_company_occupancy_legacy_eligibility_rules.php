<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection(DB::getDefaultConnection());
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['report_data_sources', 'report_definitions', 'templates'] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                return;
            }
        }

        // Re-run only the two company-occupancy report definitions so existing
        // databases receive the verified legacy eligibility rules and UI metadata.
        $finalizer = require database_path('migrations/2026_11_02_100000_finalize_company_occupancy_legacy_pdf.php');
        $finalizer->up();
    }

    public function down(): void
    {
        // Keep the legacy-aligned procedure and metadata contract in place.
    }
};
