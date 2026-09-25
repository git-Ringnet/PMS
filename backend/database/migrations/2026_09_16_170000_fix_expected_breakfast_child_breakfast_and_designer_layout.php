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

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $this->patchProcedure($connection, 'rpt_expected_breakfast_1', true);
            $this->patchProcedure($connection, 'rpt_expected_breakfast_2', false);
            $this->syncDataSourceFields($connection);
            $this->repairDesignerTemplates($connection);
        }
    }

    public function down(): void
    {
        // Keep corrected report output and saved Designer layouts on rollback.
    }

    private function patchProcedure($connection, string $procedureName, bool $hasAmounts): void
    {
        $createSql = $this->procedureSql($connection, $procedureName);
        $createSql = str_replace(
            'bc.child_status <> 3 AND bc.child_status <> 3',
            'bc.child_status <> 3',
            $createSql,
        );

        $createSql = $this->replaceChildCounts($createSql, $procedureName);
        $createSql = $this->replaceNationalityResolution($createSql);

        if ($hasAmounts) {
            $createSql = $this->replaceAmountCalculation($createSql, $procedureName);
            $createSql = $this->addExtraBreakfastTotals($createSql, $procedureName);
        }

        $connection->unprepared('DROP PROCEDURE IF EXISTS `'.$procedureName.'`');
        $connection->unprepared($createSql);
    }

    private function procedureSql($connection, string $procedureName): string
    {
        $row = (array) $connection->selectOne('SHOW CREATE PROCEDURE `'.$procedureName.'`');
        $createSql = $row['Create Procedure'] ?? null;

        if (! is_string($createSql) || $createSql === '') {
            throw new RuntimeException("Không tìm thấy stored procedure {$procedureName}.");
        }

        return $createSql;
    }

    private function replaceChildCounts(string $createSql, string $procedureName): string
    {
        if (str_contains($createSql, 'COUNT(DISTINCT bc.id)')
            && str_contains($createSql, 'COALESCE(bcbd.is_extra_charge, 0)')) {
            return $createSql;
        }

        $pattern = '/\s*COALESCE\(\s*\(SELECT COUNT\(bc\.id\)[\s\S]*?\)\s+AS children,\s*COALESCE\(\s*\(SELECT COUNT\(bc\.id\)[\s\S]*?\)\s+AS children_nk,\s*COALESCE\(\s*\(SELECT COUNT\(bc\.id\)[\s\S]*?\)\s+AS children_no_bf,/';
        $replacement = $this->childCountColumns();
        $patched = preg_replace($pattern, $replacement, $createSql, 1, $count);

        if ($count !== 1 || ! is_string($patched)) {
            throw new RuntimeException("Không chuẩn hóa được số lượng trẻ em của {$procedureName}.");
        }

        return $patched;
    }

    private function childCountColumns(): string
    {
        $serviceDate = $this->childBreakfastDate('ar');

        return <<<SQL

        (
            SELECT COUNT(DISTINCT bc.id)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = ar.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = {$serviceDate}
              AND COALESCE(bcbd.breakfast, 0) = 1
              AND (COALESCE(bcbd.amount, 0) > 0 OR COALESCE(bcbd.is_extra_charge, 0) = 1)
        ) AS children,
        (
            SELECT COUNT(DISTINCT bc.id)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = ar.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = {$serviceDate}
              AND COALESCE(bcbd.breakfast, 0) = 1
              AND COALESCE(bcbd.amount, 0) = 0
              AND COALESCE(bcbd.is_extra_charge, 0) = 0
        ) AS children_nk,
        (
            SELECT COUNT(DISTINCT bc.id)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = ar.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = {$serviceDate}
              AND COALESCE(bcbd.breakfast, 0) = 0
        ) AS children_no_bf,
SQL;
    }

    private function childBreakfastDate(string $summaryAlias): string
    {
        return <<<SQL
CASE
                  WHEN COALESCE((
                      SELECT brd.is_day_use
                      FROM booking_rooms brd
                      WHERE brd.id = {$summaryAlias}.booking_room_id
                      LIMIT 1
                  ), 0) = 1 THEN {$summaryAlias}.report_date
                  ELSE DATE_SUB({$summaryAlias}.report_date, INTERVAL 1 DAY)
              END
SQL;
    }

    private function replaceNationalityResolution(string $createSql): string
    {
        $createSql = str_replace(
            'LEFT JOIN nationalities n ON n.nationality_code = g.nationality_code',
            "LEFT JOIN nationalities n ON (\n                n.asm_code = g.nationality_code\n                OR n.nationality_id = g.nationality_code\n                OR n.nationality_id2 = g.nationality_code\n                OR n.nationality_code = g.nationality_code\n            )",
            $createSql,
        );

        $createSql = str_replace(
            'COALESCE(n.nationality_name, g.nationality_code)',
            "UPPER(COALESCE(NULLIF(n.asm_name, ''), NULLIF(n.nationality_name_en, ''), NULLIF(n.nationality_name, ''), g.nationality_code))",
            $createSql,
        );

        $createSql = str_replace(
            "COALESCE(n.nationality_name, g.nationality_code, 'Việt Nam')",
            "UPPER(COALESCE(NULLIF(n.asm_name, ''), NULLIF(n.nationality_name_en, ''), NULLIF(n.nationality_name, ''), NULLIF(g.nationality_code, ''), 'VIỆT NAM'))",
            $createSql,
        );

        return str_replace(
            ["COALESCE(NULLIF(s.nationality, ''), 'Việt Nam')", "COALESCE(s.nationality, 'Việt Nam')"],
            ["COALESCE(NULLIF(s.nationality, ''), 'VIỆT NAM')", "COALESCE(NULLIF(s.nationality, ''), 'VIỆT NAM')"],
            $createSql,
        );
    }

    private function replaceAmountCalculation(string $createSql, string $procedureName): string
    {
        if (str_contains($createSql, '+ COALESCE(breakfast_child_extra_amount, 0)')) {
            return $createSql;
        }

        $serviceDate = <<<SQL
CASE
                  WHEN COALESCE(brx.is_day_use, 0) = 1 THEN s.report_date
                  ELSE DATE_SUB(s.report_date, INTERVAL 1 DAY)
              END
SQL;

        $replacement = <<<SQL
    UPDATE tmp_room_summary AS s
    LEFT JOIN booking_rooms AS brx ON brx.id = s.booking_room_id
    SET s.breakfast_adult_amount = CASE
            WHEN COALESCE(brx.breakfast, 0) = 1 THEN COALESCE((SELECT breakfast_adult_rate FROM hotel_settings ORDER BY id LIMIT 1), 0) * COALESCE(s.adults, 0)
            ELSE 0
        END,
        s.breakfast_child_amount = COALESCE((
            SELECT SUM(CASE
                WHEN COALESCE(bcbd.breakfast, 0) = 1
                    AND COALESCE(bcbd.is_extra_charge, 0) = 0
                    THEN COALESCE(bcbd.amount, 0)
                ELSE 0
            END)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = s.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = {$serviceDate}
        ), 0),
        s.breakfast_child_extra_amount = COALESCE((
            SELECT SUM(CASE
                WHEN COALESCE(bcbd.breakfast, 0) = 1
                    AND COALESCE(bcbd.is_extra_charge, 0) = 1
                    THEN COALESCE(bcbd.amount, 0)
                ELSE 0
            END)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = s.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = {$serviceDate}
        ), 0);

    UPDATE tmp_room_summary
    SET breakfast_total_amount = COALESCE(breakfast_adult_amount, 0)
        + COALESCE(breakfast_child_amount, 0)
        + COALESCE(breakfast_child_extra_amount, 0);

SQL;

        $pattern = '/    UPDATE tmp_room_summary AS s\s+LEFT JOIN booking_rooms AS brx ON brx\.id = s\.booking_room_id\s+SET s\.breakfast_adult_amount = CASE[\s\S]*?    UPDATE tmp_room_summary\s+SET breakfast_total_amount = [\s\S]*?;\s*/';
        $patched = preg_replace($pattern, $replacement, $createSql, 1, $count);

        if ($count !== 1 || ! is_string($patched)) {
            throw new RuntimeException("Không chuẩn hóa được phụ thu ăn sáng của {$procedureName}.");
        }

        return $patched;
    }

    private function addExtraBreakfastTotals(string $createSql, string $procedureName): string
    {
        $dateMarker = "SUM(s.breakfast_child_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastChildAmount,\n        SUM(s.breakfast_total_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastTotalAmount,";
        $dateReplacement = "SUM(s.breakfast_child_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastChildAmount,\n        SUM(s.breakfast_child_extra_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastChildExtraAmount,\n        SUM(s.breakfast_total_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastTotalAmount,";
        $reportMarker = "SUM(s.breakfast_child_amount) OVER () AS ReportTotalBreakfastChildAmount,\n        SUM(s.breakfast_total_amount) OVER () AS ReportTotalBreakfastTotalAmount";
        $reportReplacement = "SUM(s.breakfast_child_amount) OVER () AS ReportTotalBreakfastChildAmount,\n        SUM(s.breakfast_child_extra_amount) OVER () AS ReportTotalBreakfastChildExtraAmount,\n        SUM(s.breakfast_total_amount) OVER () AS ReportTotalBreakfastTotalAmount";

        if (! str_contains($createSql, 'DateTotalBreakfastChildExtraAmount')) {
            if (! str_contains($createSql, $dateMarker)) {
                throw new RuntimeException("Không thêm được tổng phụ thu theo ngày của {$procedureName}.");
            }
            $createSql = str_replace($dateMarker, $dateReplacement, $createSql);
        }

        if (! str_contains($createSql, 'ReportTotalBreakfastChildExtraAmount')) {
            if (! str_contains($createSql, $reportMarker)) {
                throw new RuntimeException("Không thêm được tổng phụ thu toàn báo cáo của {$procedureName}.");
            }
            $createSql = str_replace($reportMarker, $reportReplacement, $createSql);
        }

        return $createSql;
    }

    private function syncDataSourceFields($connection): void
    {
        $source = $connection->table('report_data_sources')->where('code', 'EXPECTED_BREAKFAST')->first();
        if (! $source) {
            return;
        }

        $fields = json_decode((string) $source->field_schema, true);
        if (! is_array($fields)) {
            return;
        }

        $known = array_column($fields, 'name');
        foreach ([
            'BreakfastChildExtraAmount',
            'DateTotalBreakfastChildExtraAmount',
            'ReportTotalBreakfastChildExtraAmount',
        ] as $name) {
            if (in_array($name, $known, true)) {
                continue;
            }
            $fields[] = ['name' => $name, 'type' => 'string', 'nullable' => true];
        }

        $connection->table('report_data_sources')
            ->where('id', $source->id)
            ->update(['field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    private function repairDesignerTemplates($connection): void
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
            if (! is_array($content)) {
                continue;
            }

            if (! is_array($content['detail'] ?? null)) {
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

            $values = [];
            if ($changed) {
                $values['content_json'] = json_encode($content, JSON_UNESCAPED_UNICODE);
            }

            if ($report === 'EXPECTED_BREAKFAST_DTX_SUMMARY') {
                $html = $this->repairDtxHtml((string) $template->content_html);
                if ($html !== (string) $template->content_html) {
                    $values['content_html'] = $html;
                }

                $css = $this->repairDtxCss((string) $template->css);
                if ($css !== (string) $template->css) {
                    $values['css'] = $css;
                }
            }

            if ($values !== []) {
                $values['updated_at'] = now();
                $connection->table('templates')->where('id', $template->id)->update($values);
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
        if (is_array($block['customRows'] ?? null)) {
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
        }

        return $changed;
    }

    private function repairDtxHtml(string $html): string
    {
        $html = str_replace(
            [
                'row.BreakfastChildAmount',
                'row.DateTotalBreakfastChildAmount',
                'aggregate.rows.sum.BreakfastChildAmount',
            ],
            [
                'row.BreakfastChildExtraAmount',
                'row.DateTotalBreakfastChildExtraAmount',
                'aggregate.rows.sum.BreakfastChildExtraAmount',
            ],
            $html,
        );

        if (! str_contains($html, 'dtx-breakfast-summary-table')) {
            $html = preg_replace(
                '/(<div id="expected_breakfast_dtx_summary_table"[^>]*>\s*)<table\b([^>]*)>/s',
                '$1<table class="breakfast-summary-table dtx-breakfast-summary-table"$2>',
                $html,
                1,
            ) ?? $html;
        }

        if (! str_contains($html, 'country-summary-table')) {
            $html = preg_replace(
                '/(<div id="expected_breakfast_dtx_country_table"[^>]*>\s*)<table\b([^>]*)>/s',
                '$1<table class="country-summary-table"$2>',
                $html,
                1,
            ) ?? $html;
        }

        return preg_replace(
            '/<style>\.pms-template-block-expected_breakfast_dtx_summary_table,\s*\.pms-template-block-expected_breakfast_dtx_summary_table\s+\*\s*\{\s*font-size:\s*[^;]+;\s*\}<\/style>\s*/',
            '',
            $html,
        ) ?? $html;
    }

    private function repairDtxCss(string $css): string
    {
        $oldRule = '.dtx-breakfast-summary-table th, .dtx-breakfast-summary-table td { font-size: 8px; }';
        $newRule = '.dtx-breakfast-summary-table th, .dtx-breakfast-summary-table td { font-size: 8px !important; padding: 4px !important; line-height: 1.2; }';

        if (str_contains($css, $newRule)) {
            return $css;
        }

        return str_contains($css, $oldRule)
            ? str_replace($oldRule, $newRule, $css)
            : rtrim($css)."\n".$newRule."\n";
    }
};
