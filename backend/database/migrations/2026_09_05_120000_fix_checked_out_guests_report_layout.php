<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $templateId = DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->value('id');
        if (!$templateId) return;

        $template = require database_path('report_templates/checked_out_guests_reference.php');
        DB::table('templates')->where('id', $templateId)->update([
            'page_size'=>'A4','page_orientation'=>'portrait','margin_top'=>6,'margin_bottom'=>6,'margin_left'=>5,'margin_right'=>5,'version'=>'1.2','updated_at'=>now(),
        ]);
        $template->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update(['page_orientation'=>'landscape','version'=>'1.1','updated_at'=>now()]);
    }
};
