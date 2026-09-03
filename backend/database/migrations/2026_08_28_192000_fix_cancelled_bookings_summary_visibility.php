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

        (require database_path('report_templates/cancelled_bookings_reference.php'))->apply();
    }

    public function down(): void
    {
        // Report-specific visibility correction; restoring the blank summary is unsupported.
    }
};
