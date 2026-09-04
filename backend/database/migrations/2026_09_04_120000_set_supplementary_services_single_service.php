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

        $definition = DB::table('report_definitions')->where('code', 'SUPPLEMENTARY_SERVICES')->first();
        if (!$definition) {
            return;
        }

        $schema = json_decode($definition->parameter_ui_schema ?? '[]', true) ?: [];
        foreach ($schema as &$parameter) {
            if (($parameter['name'] ?? '') === 'p_service_codes') {
                $parameter['control'] = 'select';
                $parameter['default'] = '';
                $parameter['options_source'] = 'hotel-services';
            }
        }
        unset($parameter);

        DB::table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $definition = DB::table('report_definitions')->where('code', 'SUPPLEMENTARY_SERVICES')->first();
        if (!$definition) {
            return;
        }

        $schema = json_decode($definition->parameter_ui_schema ?? '[]', true) ?: [];
        foreach ($schema as &$parameter) {
            if (($parameter['name'] ?? '') === 'p_service_codes') {
                $parameter['control'] = 'multi-select';
                $parameter['default'] = [];
            }
        }
        unset($parameter);

        DB::table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
