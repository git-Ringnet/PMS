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

        if (! DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->exists()) {
            return;
        }

        (require database_path('report_templates/checked_out_guests_reference.php'))->apply();

        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update([
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'version' => '1.4',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update([
            'version' => '1.3',
            'updated_at' => now(),
        ]);
    }
};
