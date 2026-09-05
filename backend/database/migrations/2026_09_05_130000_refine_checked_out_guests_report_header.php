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
        (require database_path('report_templates/checked_out_guests_reference.php'))->apply();
        DB::table('templates')->where('id', $templateId)->update(['version'=>'1.2','updated_at'=>now()]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
    }
};
