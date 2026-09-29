<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $visitedDatabases = [];
        foreach ($this->targetConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $this->repairProcedure($db, 'rpt_company_occupancy', function (string $sql): string {
                if (str_contains($sql, 'SUM(x.BreakfastAmount) ELSE 0 END AS FbRevenue')) {
                    return $sql;
                }

                $needle = "SUM(CASE WHEN x.DepartmentId = 'FB' OR x.ServiceId = 'FB' THEN x.Amount ELSE 0 END) AS FbRevenue,";
                $replacement = "SUM(CASE WHEN x.DepartmentId = 'FB' OR x.ServiceId = 'FB' THEN x.Amount ELSE 0 END)\n                 + CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN SUM(x.BreakfastAmount) ELSE 0 END AS FbRevenue,";
                $patched = str_replace($needle, $replacement, $sql, $count);

                if ($count !== 1) {
                    throw new RuntimeException('Không thể cập nhật phân bổ ăn sáng của rpt_company_occupancy.');
                }

                return $patched;
            });

            $this->repairSummaryServiceInvoicesMetadata($db);
            $this->syncReferenceTemplate($db, 'SUMMARY_SERVICE_INVOICES_REFERENCE');
            $this->syncReferenceTemplate($db, 'DEPOSITS_SUMMARY_REFERENCE');
        }
    }

    public function down(): void
    {
        // Contract repair is intentionally kept when rolling back metadata migrations.
    }

    private function repairSummaryServiceInvoicesMetadata($db): void
    {
        $source = $db->table('report_data_sources')->where('code', 'RPT_SUMMARY_SERVICE_INVOICES')->first();
        if (! $source) {
            return;
        }

        $parameters = json_decode((string) $source->parameter_schema, true) ?: [];
        foreach ($parameters as &$parameter) {
            if (($parameter['name'] ?? '') === 'p_services') {
                $parameter['data_type'] = 'text';
                $parameter['database_type'] = 'text';
            }
        }
        unset($parameter);

        $fields = json_decode((string) $source->field_schema, true) ?: [];
        $byName = [];
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                $byName[$field['name']] = $field;
            }
        }
        $byName['Stt'] = ['name' => 'Stt', 'type' => 'integer', 'nullable' => true];
        $byName['ServiceCode'] = ['name' => 'ServiceCode', 'type' => 'string', 'nullable' => true];
        $orderedFields = [];
        foreach (['Stt', 'BookingCode', 'RoomNumber', 'ArrivalDate', 'DepartureDate', 'GuestName', 'Description', 'Amount', 'PaymentMethod', 'CompanyName', 'OpenTime', 'Note', 'ServiceCode', 'RevenueGroupName', 'ServiceGroupHeader', 'DateGroupHeader'] as $name) {
            if (isset($byName[$name])) {
                $orderedFields[] = $byName[$name];
            }
        }

        $db->table('report_data_sources')->where('id', $source->id)->update([
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($orderedFields, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function syncReferenceTemplate($db, string $templateCode): void
    {
        $template = $db->table('templates')->where('report', $templateCode)->first();
        if (! $template || (float) $template->version > 1.0) {
            return;
        }

        $definition = (require database_path('report_templates/'.strtolower($templateCode).'.php'))->definition();
        $db->table('templates')->where('id', $template->id)->update([
            'page_size' => $definition['page_size'],
            'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'],
            'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'],
            'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'updated_at' => now(),
        ]);
    }

    private function repairProcedure($db, string $procedure, callable $transform): void
    {
        $exists = $db->table('information_schema.routines')
            ->where('routine_schema', $db->getDatabaseName())
            ->where('routine_type', 'PROCEDURE')
            ->where('routine_name', $procedure)
            ->exists();

        if (! $exists) {
            return;
        }

        $row = $db->selectOne('SHOW CREATE PROCEDURE `'.$procedure.'`');
        $values = (array) $row;
        $definition = $values['Create Procedure'] ?? array_values($values)[1] ?? null;
        if (! is_string($definition) || $definition === '') {
            throw new RuntimeException("Không đọc được định nghĩa procedure {$procedure}.");
        }

        $patched = $transform($definition);
        if ($patched === $definition) {
            return;
        }

        $patched = preg_replace('/^CREATE DEFINER=.*? PROCEDURE /', 'CREATE PROCEDURE ', $patched, 1) ?? $patched;
        $db->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
        $db->unprepared($patched);
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }
};
