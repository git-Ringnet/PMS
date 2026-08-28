<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        (require database_path('report_templates/due_in_rooms_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'DUE_IN_ROOMS_STANDARD')->update(['content_json' => '{}', 'updated_at' => now()]);
    }
};
