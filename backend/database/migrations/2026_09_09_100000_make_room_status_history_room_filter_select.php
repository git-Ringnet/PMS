<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $report = DB::table('report_definitions')->where('code', 'ROOM_STATUS_HISTORY')->first();

        if (!$report) {
            return;
        }

        $schema = json_decode($report->parameter_ui_schema ?? '[]', true) ?: [];
        $schema = array_map(function (array $parameter): array {
            if (($parameter['name'] ?? null) === 'p_room') {
                $parameter['control'] = 'select';
                $parameter['options_source'] = 'rooms';
            }

            return $parameter;
        }, $schema);

        DB::table('report_definitions')->where('id', $report->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $report = DB::table('report_definitions')->where('code', 'ROOM_STATUS_HISTORY')->first();

        if (!$report) {
            return;
        }

        $schema = json_decode($report->parameter_ui_schema ?? '[]', true) ?: [];
        $schema = array_map(function (array $parameter): array {
            if (($parameter['name'] ?? null) === 'p_room') {
                $parameter['control'] = 'text';
                unset($parameter['options_source']);
            }

            return $parameter;
        }, $schema);

        DB::table('report_definitions')->where('id', $report->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
