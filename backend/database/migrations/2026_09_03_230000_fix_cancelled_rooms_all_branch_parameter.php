<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        $report = DB::table('report_definitions')->where('code', 'CANCELLED_ROOMS')->first();
        if (! $report) return;

        $schema = json_decode($report->parameter_ui_schema ?? '[]', true) ?: [];
        foreach ($schema as &$parameter) {
            if (($parameter['name'] ?? '') !== 'p_division') continue;

            $parameter['default'] = '__current__';
            $parameter['options'] = [
                ['value' => '__all__', 'label' => 'Tất cả chi nhánh'],
                ['value' => '__current__', 'label' => 'Chi nhánh hiện tại'],
            ];
        }
        unset($parameter);

        DB::table('report_definitions')->where('id', $report->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục giá trị rỗng vì middleware sẽ chuyển thành null.
    }
};
