<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        (require database_path('report_templates/ooo_lock_history_reference.php'))->apply();
        (require database_path('report_templates/oos_lock_history_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        foreach (['OOO_LOCK_HISTORY_STANDARD', 'OOS_LOCK_HISTORY_STANDARD'] as $report) {
            $template = DB::table('templates')->where('report', $report)->first();
            if ($template) {
                $json = json_decode($template->content_json ?: '{}', true) ?: [];
                unset($json['header']);
                DB::table('templates')->where('id', $template->id)->update(['content_json' => json_encode($json), 'updated_at' => now()]);
            }
        }
    }
};
