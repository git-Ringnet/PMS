<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROCEDURES = [
        'rpt_summary_service_invoices' => 'summaryServiceInvoicesProcedure',
        'rpt_deposits_summary' => 'depositsSummaryProcedure',
        'rpt_daily_summary' => 'dailySummaryProcedure',
        'rpt_company_occupancy_detail' => 'companyOccupancyDetailProcedure',
        'rpt_company_occupancy' => 'companyOccupancyProcedure',
        'rpt_salesperson_revenue_summary' => 'salespersonRevenueSummaryProcedure',
        'rpt_salesperson_revenue_detail' => 'salespersonRevenueDetailProcedure',
    ];

    public function up(): void
    {
        $baseMigration = require database_path('migrations/2026_09_22_170000_create_revenue_reports_154_159_160_166_167_168.php');
        $configurations = $this->invoke($baseMigration, 'configurations');
        $visitedDatabases = [];

        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            foreach (self::PROCEDURES as $procedure => $method) {
                $sql = $this->invoke($baseMigration, $method);
                $db->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
                $db->unprepared($sql);
            }

            foreach ($configurations as $configuration) {
                $this->syncConfiguration($db, $database, $baseMigration, $configuration);
            }
        }
    }

    public function down(): void
    {
        // Keep the repaired report contracts when rolling back metadata-only migrations.
    }

    private function syncConfiguration($db, string $database, object $baseMigration, array $configuration): void
    {
        $installation = $configuration['installation'];
        $ui = $this->invoke($baseMigration, 'hydrateInlineLookupOptions', [$db, $configuration['ui']]);
        $now = now();
        $db->table('report_data_sources')->updateOrInsert(['code' => $installation['source']], [
            'name' => $installation['name'],
            'description' => $configuration['description'],
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => $installation['procedure'],
            'parameter_schema' => json_encode($configuration['parameters'], JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode(array_map(static fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'nullable' => true], array_keys($configuration['fields']), $configuration['fields']), JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sourceId = $db->table('report_data_sources')->where('code', $installation['source'])->value('id');
        $definition = (require database_path('report_templates/'.strtolower($installation['template']).'.php'))->definition();
        $template = $db->table('templates')->where('report', $installation['template'])->first();
        $templateValues = [
            'report_data_source_id' => $sourceId,
            'page_size' => $definition['page_size'],
            'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'],
            'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'],
            'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'updated_at' => $now,
        ];

        if (! $template) {
            $templateValues['group'] = $installation['group'];
            $templateValues['name'] = $definition['name'];
            $templateValues['parameter_defaults'] = json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE);
            $templateValues['is_default'] = true;
            $templateValues['version'] = $definition['version'];
            $templateValues['report'] = $installation['template'];
            $templateValues['created_at'] = $now;
            $templateId = $db->table('templates')->insertGetId($templateValues);
        } else {
            $templateId = $template->id;
            if ((float) $template->version <= 1.0) {
                $db->table('templates')->where('id', $templateId)->update(array_merge($templateValues, [
                    'group' => $installation['group'],
                    'name' => $definition['name'],
                    'parameter_defaults' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                    'is_default' => true,
                    'version' => $definition['version'],
                ]));
            } else {
                // A Designer version above 1.0 is user-owned; only repair its source link.
                $db->table('templates')->where('id', $templateId)->update([
                    'report_data_source_id' => $sourceId,
                    'updated_at' => $now,
                ]);
            }
        }

        $db->table('report_definitions')->updateOrInsert(['code' => $installation['report']], [
            'name' => $installation['name'],
            'group' => $installation['group'],
            'description' => $configuration['description'],
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => $installation['sort'],
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => $installation['menu_group'],
            'menu_item_order' => $installation['sort'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', $installation['report'])->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function invoke(object $migration, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($migration, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($migration, $arguments);
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }
};
