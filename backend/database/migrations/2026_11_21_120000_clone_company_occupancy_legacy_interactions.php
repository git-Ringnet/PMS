<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection(DB::getDefaultConnection());
        if ($db->getDriverName() !== 'mysql'
            || ! $db->getSchemaBuilder()->hasTable('report_data_sources')
            || ! $db->getSchemaBuilder()->hasTable('report_definitions')
            || ! $db->getSchemaBuilder()->hasTable('templates')) {
            return;
        }

        $now = now();
        $this->appendSummaryFields($db, $now);
        $this->syncSummaryDefinition($db, $now);
        $this->syncDetailDefinition($db, $now);
        $this->syncReferenceTemplate($db, 'COMPANY_OCCUPANCY_REFERENCE', 'company_occupancy_reference.php', $now);
        $this->syncReferenceTemplate($db, 'COMPANY_OCCUPANCY_DETAIL_REFERENCE', 'company_occupancy_detail_reference.php', $now);
    }

    public function down(): void
    {
        // The migration updates user-visible report definitions and templates.
        // A destructive rollback would discard Designer changes made after deploy.
    }

    private function appendSummaryFields($db, $now): void
    {
        $source = $db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY')->first();
        if (! $source) {
            return;
        }

        $fields = json_decode((string) $source->field_schema, true);
        $fields = is_array($fields) ? $fields : [];
        $names = array_column($fields, 'name');
        foreach ([
            ['name' => 'RmRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'Revenue', 'type' => 'number', 'nullable' => true],
        ] as $field) {
            if (! in_array($field['name'], $names, true)) {
                $fields[] = $field;
            }
        }

        $db->table('report_data_sources')->where('id', $source->id)->update([
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    private function syncSummaryDefinition($db, $now): void
    {
        $definition = $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY')->first();
        if (! $definition) {
            return;
        }

        $schema = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'markets'],
            ['name' => 'p_user_sale', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            [
                'name' => 'p_show_detail', 'label' => 'Hiển thị chi tiết', 'control' => 'checkbox', 'default' => false, 'required' => false,
                'ui_only' => true, 'view_mode' => 'detail', 'linked_report_code' => 'COMPANY_OCCUPANCY_DETAIL',
                'view_mode_when' => [
                    'parameter' => 'p_group_by',
                    'equals' => ['none', ''],
                    'unless_truthy' => ['p_group_by_date', 'p_group_by_market', 'p_group_by_source'],
                ],
                'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 1, 'column' => 1],
            ],
            ['name' => 'p_include_breakfast', 'label' => 'DT phòng bao gồm AS', 'control' => 'checkbox', 'default' => true, 'required' => false, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 1, 'column' => 2]],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 2, 'column' => 1], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'date', 'priority' => 30, 'empty_value' => 'none', 'combinations' => [['with' => ['p_group_by_market'], 'value' => 'daysegment', 'priority' => 100], ['with' => ['p_group_by_source'], 'value' => 'daysourcecode', 'priority' => 100]]]],
            ['name' => 'p_group_by_market', 'label' => 'Nhóm theo thị trường', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 3, 'column' => 1], 'selection_group' => ['key' => 'company_occupancy_group_dimension', 'label' => 'Kiểu nhóm', 'mode' => 'single'], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'marketsegment', 'priority' => 20, 'empty_value' => 'none']],
            ['name' => 'p_group_by_source', 'label' => 'Nhóm theo nguồn khách', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'layout' => ['group' => 'company_occupancy_switches', 'type' => 'two-column', 'row' => 3, 'column' => 2], 'selection_group' => ['key' => 'company_occupancy_group_dimension', 'label' => 'Kiểu nhóm', 'mode' => 'single'], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'sourcecode', 'priority' => 10, 'empty_value' => 'none']],
            ['name' => 'p_group_by', 'label' => 'Nhóm theo', 'control' => 'hidden', 'default' => 'none', 'required' => false],
            // Required by sp_078 when the ungrouped detail view is selected.
            ['name' => 'p_source_code', 'label' => 'Nguồn khách', 'control' => 'hidden', 'default' => '', 'required' => false, 'ui_only' => true],
        ];

        $db->table('report_definitions')->where('id', $definition->id)->update([
            'description' => 'Clone legacy sp_055/sp_078: 11 cột, 14 cột theo nhóm hoặc 21 cột chi tiết.',
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    private function syncDetailDefinition($db, $now): void
    {
        $definition = $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY_DETAIL')->first();
        if (! $definition) {
            return;
        }

        $schema = json_decode((string) $definition->parameter_ui_schema, true);
        $schema = is_array($schema) ? $schema : [];
        $schema = array_values(array_filter($schema, static fn (array $parameter): bool => ($parameter['name'] ?? '') !== 'p_branch_context'));

        $branchContext = [
            // Legacy only shows this context control to a user assigned to multiple branches.
            // Phase 1 remains tenant-scoped; it must not be sent to sp_078.
            'name' => 'p_branch_context', 'label' => 'Chi nhánh', 'control' => 'branch-context',
            'default' => 'current', 'required' => false, 'ui_only' => true, 'requires_multiple_branches' => true,
        ];
        $insertAt = array_search('p_to_date', array_column($schema, 'name'), true);
        array_splice($schema, $insertAt === false ? 0 : $insertAt + 1, 0, [$branchContext]);

        $db->table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    private function syncReferenceTemplate($db, string $report, string $referenceFile, $now): void
    {
        $template = $db->table('templates')->where('report', $report)->first();
        if (! $template) {
            return;
        }

        $reference = require database_path('report_templates/'.$referenceFile);
        $definition = $reference->definition();
        $db->table('templates')->where('id', $template->id)->update([
            'name' => $definition['name'],
            'page_size' => $definition['page_size'],
            'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'],
            'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'],
            'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'version' => $definition['version'],
            'updated_at' => $now,
        ]);
    }
};
