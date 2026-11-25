<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection(DB::getDefaultConnection());
        $source = require database_path('migrations/2026_11_21_120000_clone_company_occupancy_legacy_interactions.php');
        $method = new \ReflectionMethod($source, 'syncSummaryDefinition');
        $method->setAccessible(true);
        $method->invoke($source, $connection, now());
    }

    public function down(): void
    {
        // Không khôi phục metadata routing cũ vì không clone được legacy detail view.
    }
};
