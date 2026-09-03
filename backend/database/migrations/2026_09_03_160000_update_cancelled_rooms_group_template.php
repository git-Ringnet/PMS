<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        (require database_path('report_templates/cancelled_rooms_reference.php'))->apply();
    }

    public function down(): void
    {
        // Layout-only correction; the previous ungrouped template is not reconstructed.
    }
};
