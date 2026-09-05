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
        $template->apply();
        DB::statement('UPDATE templates SET content_html = REPLACE(content_html, \'data-group-by="GroupKey"\', \'data-group-by="BookingId"\'), content_html = REPLACE(content_html, \'{{row.GroupLabel}}\', \'{{row.BookingName}}\'), version = \'1.3\', updated_at = NOW() WHERE id = ?', [$templateId]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::statement('UPDATE templates SET version = \'1.2\', updated_at = NOW() WHERE report = \'CHECKED_OUT_GUESTS_STANDARD\'');
    }
};
