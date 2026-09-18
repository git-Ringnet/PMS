<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'EXPECTED_BREAKFAST';
    private const REPORT = 'EXPECTED_BREAKFAST';
    private const ARMY_TEMPLATE = 'EXPECTED_BREAKFAST_ARMY_SUMMARY';
    private const DTX_TEMPLATE = 'EXPECTED_BREAKFAST_DTX_SUMMARY';
    private const DETAIL_TEMPLATE = 'EXPECTED_BREAKFAST_DETAIL';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $connections = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
        $armyProvider = require database_path('report_templates/expected_breakfast_army_summary_reference.php');
        $dtxProvider = require database_path('report_templates/expected_breakfast_dtx_summary_reference.php');
        $detailProvider = require database_path('report_templates/expected_breakfast_detail_reference.php');
        $now = now();

        foreach ($connections as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $this->patchProcedure($connection, 'rpt_expected_breakfast_1', true);
            $this->patchProcedure($connection, 'rpt_expected_breakfast_2', false);

            $connection->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast`');
            $connection->unprepared(<<<'SQL'
CREATE PROCEDURE `rpt_expected_breakfast`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_show_type TINYINT,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(30),
    IN p_sort_type VARCHAR(4),
    IN p_late_checkin TINYINT,
    IN p_show_room_details TINYINT,
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    IF COALESCE(p_show_room_details, 0) = 1 THEN
        CALL `rpt_expected_breakfast_2`(p_from_date, p_to_date, p_show_type, p_user, p_sort_by, p_sort_type, p_late_checkin, p_group_by_booking);
    ELSE
        CALL `rpt_expected_breakfast_1`(p_from_date, p_to_date, p_show_type, p_user, p_sort_by, p_sort_type, p_late_checkin, p_group_by_booking);
    END IF;
END;
SQL);

            $databaseName = $connection->getDatabaseName();
            $sourceId = $this->upsertSource($connection, $databaseName, $now);

            $armyTemplateId = $this->ensureTemplate($connection, self::ARMY_TEMPLATE, $armyProvider->definition(), $sourceId, $now);
            $dtxTemplateId = $this->ensureTemplate($connection, self::DTX_TEMPLATE, $dtxProvider->definition(), $sourceId, $now);
            $detailTemplateId = $this->ensureTemplate($connection, self::DETAIL_TEMPLATE, $detailProvider->definition(), $sourceId, $now);

            $reportId = $this->upsertReport($connection, $sourceId, $now);
            $connection->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            $connection->table('report_definition_template')->insert([
                [
                    'report_definition_id' => $reportId,
                    'template_id' => $armyTemplateId,
                    'is_default' => true,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'report_definition_id' => $reportId,
                    'template_id' => $dtxTemplateId,
                    'is_default' => false,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'report_definition_id' => $reportId,
                    'template_id' => $detailTemplateId,
                    'is_default' => false,
                    'sort_order' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $connection->table('report_definitions')
                ->whereIn('code', ['EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'])
                ->update(['is_active' => false, 'show_in_menu' => false, 'updated_at' => $now]);
            $connection->table('report_data_sources')
                ->whereIn('code', ['EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'])
                ->update(['is_active' => false, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'] as $connectionName) {
            try {
                $connection = DB::connection($connectionName);
                if ($connection->getDriverName() !== 'mysql') {
                    continue;
                }

                $connection->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast`');
                $connection->table('report_definitions')->where('code', self::REPORT)->update([
                    'is_active' => false,
                    'show_in_menu' => false,
                    'updated_at' => now(),
                ]);
                $connection->table('report_definitions')
                    ->whereIn('code', ['EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'])
                    ->update(['is_active' => true, 'show_in_menu' => true, 'updated_at' => now()]);
                $connection->table('report_data_sources')
                    ->whereIn('code', ['EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'])
                    ->update(['is_active' => true, 'updated_at' => now()]);
            } catch (\Throwable) {
                // Rollback must not prevent other branch connections from restoring their metadata.
            }
        }
    }

    private function patchProcedure($connection, string $procedureName, bool $summary): void
    {
        $row = $connection->selectOne('SHOW CREATE PROCEDURE `'.$procedureName.'`');
        $values = $row ? (array) $row : [];
        $createSql = null;
        foreach ($values as $key => $value) {
            if (strtolower((string) $key) === 'create procedure') {
                $createSql = (string) $value;
                break;
            }
        }
        if (! $createSql) {
            throw new RuntimeException("Không tìm thấy stored procedure {$procedureName}.");
        }

        $createSql = preg_replace('/(\bbooking_room_id)\s+BIGINT\b/i', '$1 VARCHAR(50)', $createSql) ?? $createSql;
        $createSql = str_replace('COALESCE(rs.is_availability, 1) = 1', 'rs.is_availability = 1', $createSql);
        $createSql = str_replace("br.arrival_time <= '00:01'", "br.arrival_time <= '09:30'", $createSql);
        $createSql = str_replace(
            "brs.service_code = 'BF' OR brs.service_code IN ('AL', 'BD', 'BE', 'BU')",
            "brs.service_code = 'BF'\n                       OR brs.service_code = COALESCE((SELECT value FROM hotel_configs WHERE name = 'Booking_BFChildSetServiceId' LIMIT 1), '')\n                       OR FIND_IN_SET(brs.service_code, COALESCE((SELECT value FROM hotel_configs WHERE name = 'ServiceBreakfastReport' LIMIT 1), '')) > 0",
            $createSql
        );
        $createSql = str_replace(
            'WHERE bc.booking_room_id = ar.booking_room_id',
            'WHERE bc.booking_room_id = ar.booking_room_id AND bc.child_status <> 3',
            $createSql
        );
        $createSql = str_replace(
            'INNER JOIN booking_children bc ON bc.booking_room_id = s.booking_room_id;',
            'INNER JOIN booking_children bc ON bc.booking_room_id = s.booking_room_id AND bc.child_status <> 3;',
            $createSql
        );

        $lateCheckinPattern = <<<'REGEX'
/AND\s+\(br\.arrival_date\s+<\s+d\.report_date\s+OR\s+br\.arrival_time\s+<=\s+'05:30'\s+OR\s+br\.arrival_time\s+<=\s+'09:30'\s*\)\s+AND\s+\(\s*br\.id\s+IN\s*\(\s*SELECT\s+lc\.booking_room_id\s+FROM\s+late_checkins\s+lc\s+WHERE\s+lc\.actual_arrival_date\s*=\s*d\.report_date\s*\)\s*OR\s*\(\s*br\.arrival_date\s*=\s*d\.report_date\s+AND\s+\(\s*br\.arrival_time\s+<=\s+'05:30'\s+OR\s+br\.arrival_time\s+<=\s+'09:30'\s*\)\s*\)\s*\)/is
REGEX;
        $lateCheckinReplacement = <<<'SQL'
AND (br.arrival_date < d.report_date OR br.arrival_time <= '09:30')
          AND br.id IN (
              SELECT lc.booking_room_id
              FROM late_checkins lc
              INNER JOIN service_bills sb ON sb.RentalRoomId1 = lc.booking_room_id
              INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
              WHERE DATE(lc.actual_arrival_date) = d.report_date
          )
SQL;
        $patched = preg_replace($lateCheckinPattern, $lateCheckinReplacement, $createSql, 1, $replacementCount);
        if ($replacementCount === 0
            && (! str_contains($createSql, 'room_night_bills') || ! str_contains($createSql, 'DATE(lc.actual_arrival_date)'))) {
            throw new RuntimeException("Không chuẩn hóa được điều kiện late check-in của {$procedureName}.");
        }
        $createSql = $replacementCount === 1 ? ($patched ?? $createSql) : $createSql;

        if ($summary) {
            $createSql = $this->addSummaryAmounts($createSql);
        }

        $connection->unprepared('DROP PROCEDURE IF EXISTS `'.$procedureName.'`');
        $connection->unprepared($createSql);
    }

    private function addSummaryAmounts(string $createSql): string
    {
        if (str_contains($createSql, 'breakfast_adult_amount DECIMAL')) {
            $columnCount = 1;
        } else {
            $createSql = preg_replace(
                '/(\bis_breakfast\s+TINYINT,)(\s+detail_room\s+TEXT,)/i',
                "$1\n        breakfast_adult_amount DECIMAL(20,6) DEFAULT 0,\n        breakfast_child_amount DECIMAL(20,6) DEFAULT 0,\n        breakfast_child_extra_amount DECIMAL(20,6) DEFAULT 0,\n        breakfast_total_amount DECIMAL(20,6) DEFAULT 0,$2",
                $createSql,
                1,
                $columnCount
            );
        }
        if ($columnCount !== 1) {
            throw new RuntimeException('Không mở rộng được schema tổng hợp báo cáo ăn sáng.');
        }

        if (! str_contains($createSql, '0 AS breakfast_adult_amount')) {
            $createSql = str_replace(
                "        '' AS detail_room\n    FROM tmp_active_rooms ar",
                "        0 AS breakfast_adult_amount,\n        0 AS breakfast_child_amount,\n        0 AS breakfast_child_extra_amount,\n        0 AS breakfast_total_amount,\n        '' AS detail_room\n    FROM tmp_active_rooms ar",
                $createSql,
                $insertColumnCount
            );
        } else {
            $insertColumnCount = 1;
        }
        if ($insertColumnCount !== 1) {
            throw new RuntimeException('Không đồng bộ được dòng insert tổng hợp báo cáo ăn sáng.');
        }

        $amountUpdate = <<<'SQL'
    UPDATE tmp_room_summary AS s
    LEFT JOIN booking_rooms AS brx ON brx.id = s.booking_room_id
    SET s.breakfast_adult_amount = CASE
            WHEN COALESCE(brx.breakfast, 0) = 1 THEN COALESCE((SELECT breakfast_adult_rate FROM hotel_settings ORDER BY id LIMIT 1), 0) * COALESCE(s.adults, 0)
            ELSE 0
        END,
        s.breakfast_child_amount = COALESCE((
            SELECT SUM(CASE WHEN bcbd.breakfast = 1 THEN COALESCE(NULLIF(bcbd.amount, 0), (SELECT breakfast_child_rate FROM hotel_settings ORDER BY id LIMIT 1)) ELSE 0 END)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = s.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = s.report_date
        ), 0),
        s.breakfast_child_extra_amount = COALESCE((
            SELECT SUM(CASE WHEN bcbd.breakfast = 1 AND bcbd.is_extra_charge = 1 THEN COALESCE(NULLIF(bcbd.amount, 0), (SELECT breakfast_child_rate FROM hotel_settings ORDER BY id LIMIT 1)) ELSE 0 END)
            FROM booking_children bc
            INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
            WHERE bc.booking_room_id = s.booking_room_id
              AND bc.child_status <> 3
              AND bcbd.service_date = s.report_date
        ), 0);

    UPDATE tmp_room_summary
    SET breakfast_total_amount = COALESCE(breakfast_adult_amount, 0) + COALESCE(breakfast_child_amount, 0);

SQL;
        if (str_contains($createSql, 'UPDATE tmp_room_summary AS s')) {
            $updateCount = 1;
        } else {
            $createSql = str_replace('    -- Filter by p_show_type', $amountUpdate.'    -- Filter by p_show_type', $createSql, $updateCount);
        }
        if ($updateCount !== 1) {
            throw new RuntimeException('Không chèn được phép tính tiền báo cáo ăn sáng.');
        }

        $selectMarker = "        s.is_breakfast AS IsBreakfast,\n        DATE_FORMAT(s.arrival_date, '%d/%m/%Y') AS ArrivalDate,";
        $selectReplacement = "        s.is_breakfast AS IsBreakfast,\n        s.breakfast_adult_amount AS BreakfastAdultAmount,\n        s.breakfast_child_amount AS BreakfastChildAmount,\n        s.breakfast_child_extra_amount AS BreakfastChildExtraAmount,\n        s.breakfast_total_amount AS BreakfastTotalAmount,\n        DATE_FORMAT(s.arrival_date, '%d/%m/%Y') AS ArrivalDate,";
        if (str_contains($createSql, 's.breakfast_adult_amount AS BreakfastAdultAmount')) {
            $selectCount = 1;
        } else {
            $createSql = str_replace($selectMarker, $selectReplacement, $createSql, $selectCount);
        }
        if ($selectCount !== 1) {
            throw new RuntimeException('Không chèn được cột tiền vào output tổng hợp.');
        }

        $totalMarker = '        SUM(s.total_pax) OVER (PARTITION BY s.report_date) AS DateTotalPax,';
        $totalReplacement = $totalMarker."\n        SUM(s.breakfast_adult_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastAdultAmount,\n        SUM(s.breakfast_child_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastChildAmount,\n        SUM(s.breakfast_total_amount) OVER (PARTITION BY s.report_date) AS DateTotalBreakfastTotalAmount,";
        if (str_contains($createSql, 'DateTotalBreakfastAdultAmount')) {
            $dateTotalCount = 1;
        } else {
            $createSql = str_replace($totalMarker, $totalReplacement, $createSql, $dateTotalCount);
        }
        if ($dateTotalCount !== 1) {
            throw new RuntimeException('Không chèn được tổng tiền theo ngày.');
        }

        $reportTotalMarker = '        SUM(s.total_pax) OVER () AS ReportTotalPax';
        $reportTotalReplacement = $reportTotalMarker.",\n        SUM(s.breakfast_adult_amount) OVER () AS ReportTotalBreakfastAdultAmount,\n        SUM(s.breakfast_child_amount) OVER () AS ReportTotalBreakfastChildAmount,\n        SUM(s.breakfast_total_amount) OVER () AS ReportTotalBreakfastTotalAmount";
        if (str_contains($createSql, 'ReportTotalBreakfastAdultAmount')) {
            $reportTotalCount = 1;
        } else {
            $createSql = str_replace($reportTotalMarker, $reportTotalReplacement, $createSql, $reportTotalCount);
        }
        if ($reportTotalCount !== 1) {
            throw new RuntimeException('Không chèn được tổng tiền toàn báo cáo.');
        }

        return $createSql;
    }

    private function upsertSource($connection, string $databaseName, $now): int
    {
        $parameterSchema = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_show_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 4, 'required' => false],
            ['name' => 'p_sort_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(30)', 'position' => 5, 'required' => true],
            ['name' => 'p_sort_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(4)', 'position' => 6, 'required' => true],
            ['name' => 'p_late_checkin', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
            ['name' => 'p_show_room_details', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 8, 'required' => false],
            ['name' => 'p_group_by_booking', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => false],
        ];

        $fields = [
            'STT', 'DateGroup', 'Date', 'BookingCode', 'Booking', 'BookingId', 'BookingName', 'Room', 'GuestName', 'CompanyName', 'Nationality',
            'Adults', 'Adult', 'Children', 'Child', 'ChildrenNK', 'ChildMP', 'ChildrenNoBreakfast', 'ChildKAS', 'TotalPax', 'Description', 'Note', 'RoomType', 'IsBreakfast',
            'BreakfastAdultAmount', 'BreakfastChildAmount', 'BreakfastChildExtraAmount', 'BreakfastTotalAmount', 'ArrivalDate', 'DepartureDate', 'DetailRoom',
            'DateTotalAdults', 'DateTotalChildren', 'DateTotalChildrenNK', 'DateTotalPax', 'DateTotalBreakfastAdultAmount', 'DateTotalBreakfastChildAmount', 'DateTotalBreakfastTotalAmount',
            'ReportTotalAdults', 'ReportTotalChildren', 'ReportTotalChildrenNK', 'ReportTotalPax', 'ReportTotalBreakfastAdultAmount', 'ReportTotalBreakfastChildAmount', 'ReportTotalBreakfastTotalAmount',
        ];
        $fieldSchema = array_map(static fn (string $name): array => [
            'name' => $name,
            'type' => in_array($name, ['BookingId'], true) ? 'integer' : 'string',
            'nullable' => true,
        ], $fields);

        $connection->table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE],
            [
                'name' => 'Dữ liệu báo cáo dự kiến khách ăn sáng',
                'description' => 'Nguồn dữ liệu dùng chung cho hai mẫu legacy sp_035 và sp_032.',
                'source_type' => 'procedure',
                'schema_name' => $databaseName,
                'object_name' => 'rpt_expected_breakfast',
                'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode([
                    'p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_show_type' => 1, 'p_user' => '',
                    'p_sort_by' => 'Room', 'p_sort_type' => 'ASC', 'p_late_checkin' => 1, 'p_show_room_details' => 0, 'p_group_by_booking' => 0,
                ]),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) $connection->table('report_data_sources')->where('code', self::SOURCE)->value('id');
    }

    private function ensureTemplate($connection, string $code, array $definition, int $sourceId, $now): int
    {
        $template = $connection->table('templates')->where('report', $code)->first();
        $values = [
            'name' => $definition['name'] ?? $code,
            'page_size' => $definition['page_size'] ?? 'A4',
            'page_orientation' => $definition['page_orientation'] ?? 'portrait',
            'margin_top' => $definition['margin_top'] ?? 6,
            'margin_bottom' => $definition['margin_bottom'] ?? 6,
            'margin_left' => $definition['margin_left'] ?? 5,
            'margin_right' => $definition['margin_right'] ?? 5,
            'version' => $definition['version'] ?? '1.0',
            'report_data_source_id' => $sourceId,
            'content_html' => $definition['content_html'] ?? '',
            'content_json' => json_encode($definition['content_json'] ?? [], JSON_UNESCAPED_UNICODE),
            'css' => $definition['css'] ?? '',
            'updated_at' => $now,
        ];

        if ($template) {
            $connection->table('templates')->where('id', $template->id)->update($values);

            return (int) $template->id;
        }

        return (int) $connection->table('templates')->insertGetId($values + [
            'group' => 'Báo cáo phòng',
            'report' => $code,
            'content_html' => $definition['content_html'] ?? '',
            'content_json' => json_encode($definition['content_json'] ?? [], JSON_UNESCAPED_UNICODE),
            'css' => $definition['css'] ?? '',
            'is_default' => false,
            'created_at' => $now,
        ]);
    }

    private function upsertReport($connection, int $sourceId, $now): int
    {
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_show_type', 'label' => 'Xem theo', 'control' => 'select', 'default' => 1, 'required' => true, 'options' => [
                ['label' => 'Phòng có ăn sáng', 'value' => 1], ['label' => 'Phòng không ăn sáng', 'value' => 2], ['label' => 'Tất cả', 'value' => 0],
            ]],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'placeholder' => 'Select Value', 'options_source' => 'users', 'options' => []],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'Room', 'required' => true, 'options' => [
                ['label' => 'Room', 'value' => 'Room'], ['label' => 'Ngày đến', 'value' => 'ArrivalDate'], ['label' => 'Ngày đi', 'value' => 'DepartureDate'],
            ]],
            ['name' => 'p_sort_type', 'label' => 'Thứ tự', 'control' => 'select', 'default' => 'ASC', 'required' => true, 'options' => [
                ['label' => 'ASC', 'value' => 'ASC'], ['label' => 'DESC', 'value' => 'DESC'],
            ]],
            ['name' => 'p_late_checkin', 'label' => 'Nhận phòng trễ', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_show_room_details', 'label' => 'Hiển thị thông tin phòng', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_booking', 'label' => 'Đăng ký theo nhóm', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];

        $connection->table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT],
            [
                'name' => 'Báo cáo dự kiến khách ăn sáng',
                'group' => 'Báo cáo phòng',
                'description' => 'Hai mẫu tổng hợp và chi tiết theo legacy sp_035/sp_032.',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => 34,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 34,
                'updated_at' => $now,
            ]
        );

        return (int) $connection->table('report_definitions')->where('code', self::REPORT)->value('id');
    }
};
