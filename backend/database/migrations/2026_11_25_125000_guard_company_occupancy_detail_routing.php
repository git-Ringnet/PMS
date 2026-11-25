<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection(DB::getDefaultConnection());
        if (! $db->getSchemaBuilder()->hasTable('report_definitions')) {
            return;
        }

        $definition = $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY')->first();
        if (! $definition) {
            return;
        }

        $schema = json_decode((string) $definition->parameter_ui_schema, true);
        if (! is_array($schema)) {
            return;
        }

        foreach ($schema as &$parameter) {
            if (($parameter['name'] ?? '') !== 'p_show_detail') {
                continue;
            }

            $parameter['view_mode_when'] = array_merge(
                is_array($parameter['view_mode_when'] ?? null) ? $parameter['view_mode_when'] : [],
                ['unless_truthy' => ['p_group_by_date', 'p_group_by_market', 'p_group_by_source']],
            );
        }
        unset($parameter);

        $db->table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep deployed report metadata intact; removing this guard can restore an incorrect legacy route.
    }
};
