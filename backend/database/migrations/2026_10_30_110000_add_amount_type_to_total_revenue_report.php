<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Re-applies 2026_10_30_100000 on databases that already ran it: the
 * "Tổng cộng / Not VAT" choice (p_amount_type) must be a procedure parameter,
 * otherwise the report API drops it from the parameter form.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require database_path('migrations/2026_10_30_100000_rebuild_total_revenue_reports.php'))->up();
    }

    public function down(): void
    {
        // Keep the report configuration recoverable; no destructive rollback is required.
    }
};
