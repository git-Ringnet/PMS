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

        (require database_path('report_templates/supplementary_services_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'SUPPLEMENTARY_SERVICES_STANDARD')->update([
            'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]),
            'updated_at' => now(),
        ]);
    }
};
