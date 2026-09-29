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

        $handledDatabases = [];
        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $databaseName = $connection->getDatabaseName();
            if (isset($handledDatabases[$databaseName])) {
                continue;
            }
            $handledDatabases[$databaseName] = true;

            $this->repairTemplates($connection);
        }
    }

    public function down(): void
    {
        // Preserve the repaired Designer binding and saved layout metadata.
    }

    private function repairTemplates($connection): void
    {
        $classesByTemplate = [
            'EXPECTED_BREAKFAST_ARMY_SUMMARY' => [
                'expected_breakfast_summary_table' => 'breakfast-summary-table',
                'expected_breakfast_country_table' => 'country-summary-table',
            ],
            'EXPECTED_BREAKFAST_DTX_SUMMARY' => [
                'expected_breakfast_dtx_summary_table' => 'breakfast-summary-table dtx-breakfast-summary-table',
                'expected_breakfast_dtx_country_table' => 'country-summary-table',
            ],
            'EXPECTED_BREAKFAST_DETAIL' => [
                'expected_breakfast_detail_table' => 'breakfast-detail-table',
            ],
        ];

        foreach ($classesByTemplate as $report => $classesByBlock) {
            $template = $connection->table('templates')->where('report', $report)->first();
            if (! $template) {
                continue;
            }

            $content = json_decode((string) $template->content_json, true);
            if (! is_array($content) || ! is_array($content['detail'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($content['detail'] as &$block) {
                $blockId = $block['id'] ?? null;
                if (! $blockId || ! isset($classesByBlock[$blockId])) {
                    continue;
                }

                if (empty($block['tableClassName'])) {
                    $block['tableClassName'] = $classesByBlock[$blockId];
                    $changed = true;
                }

                if ($report === 'EXPECTED_BREAKFAST_DTX_SUMMARY') {
                    $changed = $this->repairDtxBindings($block) || $changed;
                }
            }
            unset($block);

            if ($changed) {
                $connection->table('templates')
                    ->where('id', $template->id)
                    ->update([
                        'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function repairDtxBindings(array &$block): bool
    {
        $changed = false;

        if (is_array($block['columns'] ?? null)) {
            foreach ($block['columns'] as &$column) {
                if (($column['header'] ?? null) === 'Phụ Thu AS T.E'
                    && ($column['value'] ?? null) === 'row.BreakfastChildAmount') {
                    $column['value'] = 'row.BreakfastChildExtraAmount';
                    $changed = true;
                }
            }
            unset($column);
        }

        $bindings = [
            'dtx_date_child_amount' => 'row.DateTotalBreakfastChildExtraAmount',
            'dtx_total_child_amount' => 'aggregate.rows.sum.BreakfastChildExtraAmount',
        ];
        if (! is_array($block['customRows'] ?? null)) {
            return $changed;
        }

        foreach ($block['customRows'] as &$row) {
            if (! is_array($row['cells'] ?? null)) {
                continue;
            }

            foreach ($row['cells'] as &$cell) {
                $target = $bindings[$cell['id'] ?? ''] ?? null;
                if ($target && ($cell['binding'] ?? null) !== $target) {
                    $cell['binding'] = $target;
                    $changed = true;
                }
            }
            unset($cell);
        }
        unset($row);

        return $changed;
    }
};
