<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::getDefaultConnection();
        $db = DB::connection($connection);
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy`');
        $db->unprepared($this->summaryProcedure());
        $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy_detail`');
        $db->unprepared($this->detailProcedure());

        $this->syncConfiguration($db);
    }

    public function down(): void
    {
        // Không rollback procedure/template đã được dùng để đối soát legacy.
    }

    private function syncConfiguration($db): void
    {
        $database = $db->getDatabaseName();
        $now = now();
        $configurations = [
            [
                'source' => 'RPT_COMPANY_OCCUPANCY',
                'report' => 'COMPANY_OCCUPANCY',
                'template' => 'COMPANY_OCCUPANCY_REFERENCE',
                'procedure' => 'rpt_company_occupancy',
                'name' => 'Báo cáo công suất công ty',
                'description' => 'Đối soát theo SalesCompanyReportRoRate.pdf; dữ liệu tổng hợp theo công ty.',
                'parameters' => $this->summaryParameters(),
                'fields' => $this->summaryFields(),
                'defaults' => [
                    'p_from_date' => now()->toDateString(),
                    'p_to_date' => now()->toDateString(),
                    'p_area' => '',
                    'p_company' => '',
                    'p_segment' => '',
                    'p_user_sale' => '',
                    'p_group_by' => 'none',
                    'p_include_breakfast' => 1,
                ],
                'ui' => $this->summaryUi(),
                'sort' => 167,
            ],
            [
                'source' => 'RPT_COMPANY_OCCUPANCY_DETAIL',
                'report' => 'COMPANY_OCCUPANCY_DETAIL',
                'template' => 'COMPANY_OCCUPANCY_DETAIL_REFERENCE',
                'procedure' => 'rpt_company_occupancy_detail',
                'name' => 'Báo cáo chi tiết công suất công ty',
                'description' => 'Đối soát theo SaleCompanyReportDetailList.pdf; nhóm booking theo công ty.',
                'parameters' => $this->detailParameters(),
                'fields' => $this->detailFields(),
                'defaults' => [
                    'p_from_date' => now()->toDateString(),
                    'p_to_date' => now()->toDateString(),
                    'p_area' => '',
                    'p_company' => '',
                    'p_segment' => '',
                    'p_user_sale' => '',
                    'p_source_code' => '',
                ],
                'ui' => $this->detailUi(),
                'sort' => 166,
            ],
        ];

        foreach ($configurations as $configuration) {
            $ui = $this->hydrateLookupOptions($db, $configuration['ui']);
            $db->table('report_data_sources')->updateOrInsert(
                ['code' => $configuration['source']],
                [
                    'name' => $configuration['name'],
                    'description' => $configuration['description'],
                    'source_type' => 'procedure',
                    'schema_name' => $database,
                    'object_name' => $configuration['procedure'],
                    'parameter_schema' => json_encode($configuration['parameters'], JSON_UNESCAPED_UNICODE),
                    'field_schema' => json_encode(array_map(
                        static fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'nullable' => true],
                        array_keys($configuration['fields']),
                        $configuration['fields']
                    ), JSON_UNESCAPED_UNICODE),
                    'sample_parameters' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                    'max_rows' => 5000,
                    'is_active' => true,
                    'last_discovered_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $sourceId = $db->table('report_data_sources')->where('code', $configuration['source'])->value('id');
            $definition = (require database_path('report_templates/'.strtolower($configuration['template']).'.php'))->definition();
            $templateId = $db->table('templates')->where('report', $configuration['template'])->value('id');
            $templateValues = [
                'group' => 'Báo cáo công suất',
                'name' => $definition['name'],
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                'page_size' => $definition['page_size'],
                'page_orientation' => $definition['page_orientation'],
                'margin_top' => $definition['margin_top'],
                'margin_right' => $definition['margin_right'],
                'margin_bottom' => $definition['margin_bottom'],
                'margin_left' => $definition['margin_left'],
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'],
                'css' => $definition['css'],
                'is_default' => true,
                'version' => $definition['version'],
                'updated_at' => $now,
            ];
            if ($templateId) {
                $db->table('templates')->where('id', $templateId)->update($templateValues);
            } else {
                $templateId = $db->table('templates')->insertGetId([
                    ...$templateValues,
                    'report' => $configuration['template'],
                    'created_at' => $now,
                ]);
            }

            $reportValues = [
                'name' => $configuration['name'],
                'group' => 'Báo cáo công suất',
                'description' => $configuration['description'],
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => $configuration['sort'],
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk'], JSON_UNESCAPED_UNICODE),
                'menu_top_order' => 20,
                'menu_group_order' => 30,
                'menu_item_order' => $configuration['sort'],
                'updated_at' => $now,
            ];
            $db->table('report_definitions')->updateOrInsert(['code' => $configuration['report']], $reportValues + ['created_at' => $now]);
            $reportId = $db->table('report_definitions')->where('code', $configuration['report'])->value('id');
            $db->table('report_definition_template')->updateOrInsert(
                ['report_definition_id' => $reportId, 'template_id' => $templateId],
                ['is_default' => true, 'sort_order' => 0, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    private function hydrateLookupOptions($db, array $ui): array
    {
        foreach ($ui as &$field) {
            if (($field['name'] ?? '') === 'p_segment') {
                $field['options'] = array_merge(
                    [['value' => '', 'label' => 'Tất cả']],
                    $db->table('markets')->orderBy('code')->get(['code', 'name'])
                        ->map(fn ($row): array => ['value' => $row->code, 'label' => trim($row->code.' - '.$row->name, ' -')])->all()
                );
            }
            if (($field['name'] ?? '') === 'p_source_code') {
                $field['options'] = array_merge(
                    [['value' => '', 'label' => 'Tất cả']],
                    $db->table('customer_sources')->orderBy('code')->get(['code', 'name'])
                        ->map(fn ($row): array => ['value' => $row->code, 'label' => trim($row->code.' - '.$row->name, ' -')])->all()
                );
            }
        }
        unset($field);

        return $ui;
    }

    private function summaryParameters(): array
    {
        return $this->parameterList([
            ['p_from_date', 'date'], ['p_to_date', 'date'], ['p_area', 'varchar'], ['p_company', 'varchar'],
            ['p_segment', 'varchar'], ['p_user_sale', 'varchar'], ['p_group_by', 'varchar'], ['p_include_breakfast', 'tinyint'],
        ]);
    }

    private function detailParameters(): array
    {
        return $this->parameterList([
            ['p_from_date', 'date'], ['p_to_date', 'date'], ['p_area', 'varchar'], ['p_company', 'varchar'],
            ['p_segment', 'varchar'], ['p_user_sale', 'varchar'], ['p_source_code', 'varchar'],
        ]);
    }

    private function parameterList(array $items): array
    {
        return array_map(static fn (array $item, int $index): array => [
            'name' => $item[0], 'mode' => 'IN', 'data_type' => $item[1], 'database_type' => $item[1],
            'position' => $index + 1, 'required' => true,
        ], $items, array_keys($items));
    }

    private function summaryFields(): array
    {
        return [
            'CompanyCode' => 'string', 'CompanyName' => 'string', 'TotalRevenue' => 'number', 'RevenuePercentage' => 'number',
            'AverageRevenue' => 'number', 'RoomQty' => 'integer', 'RoomNight' => 'integer', 'FocRoomNights' => 'integer',
            'HuRoomNights' => 'integer', 'OccupancyRate' => 'number', 'GuestQty' => 'integer', 'GuestNight' => 'integer',
            'RoomRevenue' => 'number', 'ExtraBedRevenue' => 'number', 'ChildSurchargeRevenue' => 'number',
            'FbRevenue' => 'number', 'OtherRevenue' => 'number', 'ActualADR' => 'number', 'RackADR' => 'number',
            'TotalRoomAvailable' => 'integer', 'RoomAvailable' => 'integer', 'BookingCount' => 'integer', 'ActivityDate' => 'string', 'GroupHeader' => 'string',
            'GroupHeaderId' => 'string', 'Foc' => 'integer', 'Hu' => 'integer',
        ];
    }

    private function detailFields(): array
    {
        return [
            'BookingCode' => 'string', 'ReferenceCode' => 'string', 'CompanyName' => 'string', 'GuestName' => 'string',
            'MarketSegment' => 'string', 'SourceCode' => 'string', 'BookingDate' => 'string', 'ArrivalDate' => 'string',
            'DepartureDate' => 'string', 'NoOfNight' => 'integer', 'NoOfRoom' => 'integer', 'RoomNight' => 'integer',
            'GuestNight' => 'integer', 'AverageRate' => 'number', 'AverageRateOriginal' => 'number', 'RoomRevenue' => 'number',
            'FbRevenue' => 'number', 'OtherRevenue' => 'number', 'TotalRevenue' => 'number', 'RoomType' => 'string',
            'Nationality' => 'string', 'CompanyCode' => 'string', 'RowNumber' => 'integer', 'ExtraBedRevenue' => 'number',
            'ChildSurchargeRevenue' => 'number',
        ];
    }

    private function summaryUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_user_sale', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            ['name' => 'p_show_detail', 'label' => 'Hiển thị chi tiết', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'view_mode' => 'detail', 'linked_report_code' => 'COMPANY_OCCUPANCY_DETAIL'],
            ['name' => 'p_source_code', 'label' => 'Nguồn khách', 'control' => 'select', 'default' => '', 'required' => false, 'ui_only' => true, 'visible_when' => ['parameter' => 'p_show_detail', 'truthy' => true], 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_include_breakfast', 'label' => 'DT phòng bao gồm AS', 'control' => 'checkbox', 'default' => true, 'required' => false, 'visible_when' => ['parameter' => 'p_show_detail', 'truthy' => false]],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'visible_when' => ['parameter' => 'p_show_detail', 'truthy' => false], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'date', 'priority' => 30, 'empty_value' => 'none', 'combinations' => [['with' => ['p_group_by_market'], 'value' => 'daysegment', 'priority' => 100], ['with' => ['p_group_by_source'], 'value' => 'daysourcecode', 'priority' => 100]]]],
            ['name' => 'p_group_by_market', 'label' => 'Nhóm theo thị trường', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'visible_when' => ['parameter' => 'p_show_detail', 'truthy' => false], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'marketsegment', 'priority' => 20, 'empty_value' => 'none', 'exclusive_with' => ['p_group_by_source']]],
            ['name' => 'p_group_by_source', 'label' => 'Nhóm theo nguồn khách', 'control' => 'checkbox', 'default' => false, 'required' => false, 'ui_only' => true, 'visible_when' => ['parameter' => 'p_show_detail', 'truthy' => false], 'maps_to' => ['parameter' => 'p_group_by', 'value' => 'sourcecode', 'priority' => 10, 'empty_value' => 'none', 'exclusive_with' => ['p_group_by_market']]],
            ['name' => 'p_group_by', 'label' => 'Nhóm theo', 'control' => 'hidden', 'default' => 'none', 'required' => false],
        ];
    }

    private function detailUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_source_code', 'label' => 'Nguồn khách', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_user_sale', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
        ];
    }

    private function summaryProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(50),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(50),
    IN p_user_sale VARCHAR(50),
    IN p_group_by VARCHAR(30),
    IN p_include_breakfast TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, COALESCE(p_from_date, CURRENT_DATE()));
    DECLARE v_system_date DATE DEFAULT NULL;
    DECLARE v_operation_date DATE DEFAULT NULL;
    DECLARE v_from_past DATE DEFAULT NULL;
    DECLARE v_to_past DATE DEFAULT NULL;
    DECLARE v_from_future DATE DEFAULT NULL;
    DECLARE v_to_future DATE DEFAULT NULL;
    DECLARE v_current_date DATE DEFAULT NULL;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_capacity INT DEFAULT 0;
    DECLARE v_type_group VARCHAR(30) DEFAULT 'none';
    DECLARE v_er_services TEXT DEFAULT 'ER';
    DECLARE v_total_revenue DECIMAL(18,2) DEFAULT 0;
    DECLARE v_prefix VARCHAR(50) DEFAULT '';

    IF v_to < v_from THEN SET v_to = v_from; END IF;
    SET v_type_group = LOWER(COALESCE(NULLIF(p_group_by, ''), 'none'));
    IF v_type_group = 'company' THEN SET v_type_group = 'none'; END IF;
    IF v_type_group = 'market' THEN SET v_type_group = 'marketsegment'; END IF;
    IF v_type_group = 'source' THEN SET v_type_group = 'sourcecode'; END IF;
    IF v_type_group = 'day_market' THEN SET v_type_group = 'daysegment'; END IF;
    IF v_type_group = 'day_source' THEN SET v_type_group = 'daysourcecode'; END IF;

    SELECT CAST(value AS DATE) INTO v_operation_date
      FROM hotel_configs
     WHERE LOWER(name) = 'operationdate' AND value IS NOT NULL AND value <> ''
     LIMIT 1;
    IF v_operation_date IS NOT NULL AND v_from < v_operation_date THEN SET v_from = v_operation_date; END IF;

    SELECT CAST(value AS DATE) INTO v_system_date
      FROM hotel_configs
     WHERE LOWER(name) = 'systemdate' AND value IS NOT NULL AND value <> ''
     LIMIT 1;
    IF v_system_date IS NULL THEN
        SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;

    SET v_from_past = v_from;
    SET v_to_past = IF(v_to < v_system_date, v_to, DATE_SUB(v_system_date, INTERVAL 1 DAY));
    SET v_from_future = IF(v_system_date <= v_from, v_from, v_system_date);
    SET v_to_future = v_to;

    SET v_er_services = 'ER';
    SELECT COALESCE(NULLIF(value, ''), v_er_services) INTO v_er_services
      FROM hotel_configs WHERE LOWER(name) = 'revenueer' LIMIT 1;
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;

    SELECT COUNT(*) INTO v_total_rooms
      FROM rooms
     WHERE COALESCE(is_internal, 0) = 0
       AND room_number NOT LIKE '0%';
    IF v_total_rooms <= 0 THEN SET v_total_rooms = 1; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_calendar;
    CREATE TEMPORARY TABLE tmp_company_occupancy_calendar (
        cal_date DATE PRIMARY KEY,
        ooo_rooms INT DEFAULT 0,
        room_available INT DEFAULT 0
    );
    SET v_current_date = v_from;
    WHILE v_current_date <= v_to DO
        INSERT INTO tmp_company_occupancy_calendar (cal_date, room_available) VALUES (v_current_date, v_total_rooms);
        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
    END WHILE;
    UPDATE tmp_company_occupancy_calendar c
       SET c.ooo_rooms = (SELECT COUNT(DISTINCT rl.room_number) FROM room_locks rl
                           WHERE COALESCE(rl.is_active, 1) = 1 AND UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO'
                             AND c.cal_date BETWEEN DATE(rl.start_date) AND DATE(rl.end_date));
    UPDATE tmp_company_occupancy_calendar SET room_available = GREATEST(v_total_rooms - ooo_rooms, 0);
    UPDATE tmp_company_occupancy_calendar c
    INNER JOIN (SELECT snapshot_date, SUM(room_available) AS room_available
                  FROM night_audit_room_type_snapshots GROUP BY snapshot_date) s
       ON s.snapshot_date = c.cal_date
       SET c.room_available = s.room_available
     WHERE c.cal_date < v_system_date AND s.room_available > 0;
    SELECT COALESCE(SUM(room_available), 1) INTO v_capacity FROM tmp_company_occupancy_calendar;
    IF v_capacity <= 0 THEN SET v_capacity = 1; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_items;
    CREATE TEMPORARY TABLE tmp_company_occupancy_items (
        BookingId BIGINT,
        CompanyCode VARCHAR(50), CompanyName VARCHAR(255),
        MarketCode VARCHAR(50), MarketName VARCHAR(255),
        SourceCode VARCHAR(50), SourceName VARCHAR(255), ActivityDate DATE,
        RoomQty INT DEFAULT 0, GuestQty INT DEFAULT 0, RoomNight INT DEFAULT 0, GuestNight INT DEFAULT 0,
        FocRoomNights INT DEFAULT 0, HuRoomNights INT DEFAULT 0,
        RoomRevenue DECIMAL(18,2) DEFAULT 0, ExtraBedRevenue DECIMAL(18,2) DEFAULT 0,
        ChildSurchargeRevenue DECIMAL(18,2) DEFAULT 0
    );

    IF v_from_past <= v_to_past THEN
        INSERT INTO tmp_company_occupancy_items
        SELECT b.id, COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'),
               COALESCE(m.code, ''), COALESCE(NULLIF(m.name, ''), m.code, ''),
               COALESCE(cs.code, ''), COALESCE(NULLIF(cs.name, ''), cs.code, ''), DATE(COALESCE(rnb.date, sb.Date)),
               COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               COALESCE((SELECT SUM(COALESCE(br0.adults, 0) + COALESCE(br0.children_qty, 0) + COALESCE(br0.babies, 0)) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN COALESCE(NULLIF(COALESCE(rnb.adult, 0) + COALESCE(rnb.child, 0), 0), COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0), 0) ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND (COALESCE(rnb.rate_code, '') = 'FOC' OR COALESCE(rnb.rate, 0) = 0) THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.rate_code, '') = 'HU' THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' THEN COALESCE(sb.Amount, 0) - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN COALESCE(rnb.breakfast_amount, 0) ELSE 0 END ELSE 0 END,
               CASE WHEN sb.ServiceId = 'EB' THEN COALESCE(sb.Amount, 0) ELSE 0 END,
               CASE WHEN FIND_IN_SET(sb.ServiceId, REPLACE(v_er_services, ' ', '')) > 0 THEN COALESCE(sb.Amount, 0) ELSE 0 END
          FROM service_bills sb
          LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
          LEFT JOIN bookings b ON b.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))
          LEFT JOIN companies c ON c.id = COALESCE(b.company_id, sb.CompanyId2)
          LEFT JOIN markets m ON m.id = b.market_id
          LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
          LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
          LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
          LEFT JOIN rooms r ON r.room_number = br.room_number
         WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
           AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN v_from_past AND v_to_past
           AND b.id IS NOT NULL AND b.deleted_at IS NULL AND b.status NOT IN (3, 4)
           AND COALESCE(rs.is_availability, 1) = 1
           AND (br.room_number IS NULL OR (COALESCE(r.is_internal, 0) = 0 AND br.room_number NOT LIKE '0%'))
           AND EXISTS (SELECT 1 FROM booking_rooms brp INNER JOIN rooms rp ON rp.room_number = brp.room_number WHERE brp.booking_id = b.id AND brp.status NOT IN (3, 100) AND COALESCE(rp.is_internal, 0) = 0 AND brp.room_number NOT LIKE '0%')
           AND (sb.ServiceId IN ('RM', 'EB') OR FIND_IN_SET(sb.ServiceId, REPLACE(v_er_services, ' ', '')) > 0)
           AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
           AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
           AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
           AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number WHERE bra.booking_id = b.id AND rra.area = p_area));
    END IF;

    IF v_from_future <= v_to_future THEN
        INSERT INTO tmp_company_occupancy_items
        SELECT b.id, COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'),
               COALESCE(m.code, ''), COALESCE(NULLIF(m.name, ''), m.code, ''),
               COALESCE(cs.code, ''), COALESCE(NULLIF(cs.name, ''), cs.code, ''), tc.cal_date,
               COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               COALESCE((SELECT SUM(COALESCE(br0.adults, 0) + COALESCE(br0.children_qty, 0) + COALESCE(br0.babies, 0)) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               1, COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0),
               CASE WHEN COALESCE(br.rate_code, '') = 'FOC' OR COALESCE(br.rate, 0) = 0 THEN 1 ELSE 0 END,
               CASE WHEN COALESCE(br.rate_code, '') = 'HU' THEN 1 ELSE 0 END,
               COALESCE(br.rate, 0), 0, 0
          FROM booking_rooms br
          INNER JOIN bookings b ON b.id = br.booking_id
          INNER JOIN tmp_company_occupancy_calendar tc ON tc.cal_date >= v_from_future AND tc.cal_date <= v_to_future
                                                       AND tc.cal_date >= br.arrival_date AND tc.cal_date < br.departure_date
          LEFT JOIN companies c ON c.id = b.company_id
          LEFT JOIN markets m ON m.id = b.market_id
          LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
          LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
          INNER JOIN rooms r ON r.room_number = br.room_number
         WHERE b.deleted_at IS NULL AND b.status NOT IN (3, 4) AND br.status NOT IN (3, 100)
           AND COALESCE(rs.is_availability, 1) = 1
           AND COALESCE(r.is_internal, 0) = 0 AND br.room_number NOT LIKE '0%'
           AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
           AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
           AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
           AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM rooms rra WHERE rra.room_number = br.room_number AND rra.area = p_area));
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_days;
    CREATE TEMPORARY TABLE tmp_company_occupancy_days AS
    SELECT BookingId, CompanyCode, CompanyName, MarketCode, MarketName, SourceCode, SourceName, ActivityDate,
           MAX(RoomQty) AS RoomQty, MAX(GuestQty) AS GuestQty, SUM(RoomNight) AS RoomNight, SUM(GuestNight) AS GuestNight,
           SUM(FocRoomNights) AS FocRoomNights, SUM(HuRoomNights) AS HuRoomNights,
           SUM(RoomRevenue) AS RoomRevenue, SUM(ExtraBedRevenue) AS ExtraBedRevenue, SUM(ChildSurchargeRevenue) AS ChildSurchargeRevenue
      FROM tmp_company_occupancy_items
     GROUP BY BookingId, CompanyCode, CompanyName, MarketCode, MarketName, SourceCode, SourceName, ActivityDate;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_summary;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_booking_first;
    CREATE TEMPORARY TABLE tmp_company_occupancy_booking_first AS
    SELECT BookingId, MIN(ActivityDate) AS FirstActivityDate
      FROM tmp_company_occupancy_days
     GROUP BY BookingId;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_group_rows;
    CREATE TEMPORARY TABLE tmp_company_occupancy_group_rows AS
    SELECT d.*,
           CASE v_type_group
               WHEN 'date' THEN DATE_FORMAT(d.ActivityDate, '%Y-%m-%d')
               WHEN 'marketsegment' THEN COALESCE(NULLIF(d.MarketCode, ''), 'UNKNOWN')
               WHEN 'sourcecode' THEN COALESCE(NULLIF(d.SourceCode, ''), 'UNKNOWN')
               WHEN 'daysegment' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%Y-%m-%d'), '_', COALESCE(NULLIF(d.MarketCode, ''), 'UNKNOWN'))
               WHEN 'daysourcecode' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%Y-%m-%d'), '_', COALESCE(NULLIF(d.SourceCode, ''), 'UNKNOWN'))
               ELSE d.CompanyCode
           END AS GroupKey,
           CASE v_type_group
               WHEN 'date' THEN DATE_FORMAT(d.ActivityDate, '%d/%m/%Y')
               WHEN 'marketsegment' THEN COALESCE(NULLIF(d.MarketName, ''), d.MarketCode, 'Không xác định')
               WHEN 'sourcecode' THEN COALESCE(NULLIF(d.SourceName, ''), d.SourceCode, 'Không xác định')
               WHEN 'daysegment' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%d/%m/%Y'), ' - ', COALESCE(NULLIF(d.MarketName, ''), d.MarketCode, 'Không xác định'))
               WHEN 'daysourcecode' THEN CONCAT(DATE_FORMAT(d.ActivityDate, '%d/%m/%Y'), ' - ', COALESCE(NULLIF(d.SourceName, ''), d.SourceCode, 'Không xác định'))
               ELSE d.CompanyName
           END AS GroupName,
           CASE WHEN v_type_group IN ('date', 'daysegment', 'daysourcecode') OR d.ActivityDate = bf.FirstActivityDate THEN d.RoomQty ELSE 0 END AS CountedRoomQty,
           CASE WHEN v_type_group IN ('date', 'daysegment', 'daysourcecode') OR d.ActivityDate = bf.FirstActivityDate THEN d.GuestQty ELSE 0 END AS CountedGuestQty
      FROM tmp_company_occupancy_days d
      LEFT JOIN tmp_company_occupancy_booking_first bf ON bf.BookingId = d.BookingId;

    CREATE TEMPORARY TABLE tmp_company_occupancy_summary AS
    SELECT GroupKey AS CompanyCode, GroupName AS CompanyName,
           SUM(CountedRoomQty) AS RoomQty,
           SUM(RoomNight) AS RoomNight, SUM(GuestNight) AS GuestNight,
           SUM(CountedGuestQty) AS GuestQty,
           SUM(FocRoomNights) AS FocRoomNights, SUM(HuRoomNights) AS HuRoomNights,
           SUM(RoomRevenue) AS RoomRevenue, SUM(ExtraBedRevenue) AS ExtraBedRevenue, SUM(ChildSurchargeRevenue) AS ChildSurchargeRevenue,
           COUNT(DISTINCT BookingId) AS BookingCount, MIN(ActivityDate) AS ActivityDate,
           v_capacity AS RoomAvailable
      FROM tmp_company_occupancy_group_rows
     GROUP BY GroupKey, GroupName;

    SELECT COALESCE(SUM(RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue), 0) INTO v_total_revenue FROM tmp_company_occupancy_summary;
    SELECT CompanyCode, CompanyName,
           ROUND(RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue, 0) AS TotalRevenue,
           ROUND(CASE WHEN v_total_revenue = 0 THEN 0 ELSE (RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue) * 100 / v_total_revenue END, 2) AS RevenuePercentage,
           ROUND(CASE WHEN (RoomNight - HuRoomNights) = 0 THEN 0 ELSE (RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue) / (RoomNight - HuRoomNights) END, 0) AS AverageRevenue,
           CAST(RoomQty AS SIGNED) AS RoomQty, CAST(RoomNight AS SIGNED) AS RoomNight, CAST(FocRoomNights AS SIGNED) AS FocRoomNights,
           CAST(HuRoomNights AS SIGNED) AS HuRoomNights, ROUND(RoomNight * 100 / v_capacity, 2) AS OccupancyRate,
           CAST(GuestQty AS SIGNED) AS GuestQty, CAST(GuestNight AS SIGNED) AS GuestNight,
           ROUND(RoomRevenue, 0) AS RoomRevenue, ROUND(ExtraBedRevenue, 0) AS ExtraBedRevenue, ROUND(ChildSurchargeRevenue, 0) AS ChildSurchargeRevenue,
            0 AS FbRevenue, ROUND(ChildSurchargeRevenue, 0) AS OtherRevenue,
            ROUND(CASE WHEN RoomNight = 0 THEN 0 ELSE RoomRevenue / RoomNight END, 0) AS ActualADR,
            ROUND(CASE WHEN RoomNight - HuRoomNights = 0 THEN 0 ELSE RoomRevenue / (RoomNight - HuRoomNights) END, 0) AS RackADR,
            CAST(RoomAvailable AS SIGNED) AS TotalRoomAvailable, CAST(RoomAvailable AS SIGNED) AS RoomAvailable, CAST(BookingCount AS SIGNED) AS BookingCount,
           DATE_FORMAT(ActivityDate, '%d/%m/%Y') AS ActivityDate, CompanyName AS GroupHeader, CompanyCode AS GroupHeaderId,
           CAST(FocRoomNights AS SIGNED) AS Foc, CAST(HuRoomNights AS SIGNED) AS Hu
      FROM tmp_company_occupancy_summary
     ORDER BY TotalRevenue DESC, CompanyName ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_summary;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_group_rows;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_booking_first;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_days;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_items;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_calendar;
END
SQL;
    }

    private function detailProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy_detail(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(50),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(50),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, COALESCE(p_from_date, CURRENT_DATE()));
    DECLARE v_system_date DATE DEFAULT NULL;
    DECLARE v_operation_date DATE DEFAULT NULL;
    DECLARE v_from_past DATE DEFAULT NULL;
    DECLARE v_to_past DATE DEFAULT NULL;
    DECLARE v_from_future DATE DEFAULT NULL;
    DECLARE v_to_future DATE DEFAULT NULL;
    DECLARE v_current_date DATE DEFAULT NULL;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    DECLARE v_er_services TEXT DEFAULT 'ER';

    IF v_to < v_from THEN SET v_to = v_from; END IF;
    SELECT CAST(value AS DATE) INTO v_operation_date FROM hotel_configs WHERE LOWER(name) = 'operationdate' AND value IS NOT NULL AND value <> '' LIMIT 1;
    IF v_operation_date IS NOT NULL AND v_from < v_operation_date THEN SET v_from = v_operation_date; END IF;
    SELECT CAST(value AS DATE) INTO v_system_date FROM hotel_configs WHERE LOWER(name) = 'systemdate' AND value IS NOT NULL AND value <> '' LIMIT 1;
    IF v_system_date IS NULL THEN SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1; END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;
    SET v_from_past = v_from;
    SET v_to_past = IF(v_to < v_system_date, v_to, DATE_SUB(v_system_date, INTERVAL 1 DAY));
    SET v_from_future = IF(v_system_date <= v_from, v_from, v_system_date);
    SET v_to_future = v_to;
    SET v_er_services = 'ER';
    SELECT COALESCE(NULLIF(value, ''), v_er_services) INTO v_er_services FROM hotel_configs WHERE LOWER(name) = 'revenueer' LIMIT 1;
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;
    SELECT COUNT(*) INTO v_total_rooms
      FROM rooms
     WHERE COALESCE(is_internal, 0) = 0
       AND room_number NOT LIKE '0%';
    IF v_total_rooms <= 0 THEN SET v_total_rooms = 1; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_calendar;
    CREATE TEMPORARY TABLE tmp_company_detail_calendar (cal_date DATE PRIMARY KEY, room_available INT DEFAULT 0);
    SET v_current_date = v_from;
    WHILE v_current_date <= v_to DO
        INSERT INTO tmp_company_detail_calendar (cal_date, room_available) VALUES (v_current_date, v_total_rooms);
        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
    END WHILE;
    UPDATE tmp_company_detail_calendar c SET c.room_available = GREATEST(v_total_rooms - (
        SELECT COUNT(DISTINCT rl.room_number) FROM room_locks rl
         WHERE COALESCE(rl.is_active, 1) = 1 AND UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO'
           AND c.cal_date BETWEEN DATE(rl.start_date) AND DATE(rl.end_date)
    ), 0);
    UPDATE tmp_company_detail_calendar c
    INNER JOIN (SELECT snapshot_date, SUM(room_available) AS room_available FROM night_audit_room_type_snapshots GROUP BY snapshot_date) s ON s.snapshot_date = c.cal_date
       SET c.room_available = s.room_available
     WHERE s.room_available > 0 AND c.cal_date < v_system_date;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_items;
    CREATE TEMPORARY TABLE tmp_company_detail_items (
        BookingId BIGINT, BookingCode VARCHAR(100), ReferenceCode VARCHAR(100), CompanyCode VARCHAR(50), CompanyName VARCHAR(255),
        GuestName VARCHAR(255), MarketSegment VARCHAR(50), SourceCode VARCHAR(50), BookingDate DATE,
        ArrivalDate DATE, DepartureDate DATE, NoOfNight INT DEFAULT 0, NoOfRoom INT DEFAULT 0, ActivityDate DATE,
        RoomQty INT DEFAULT 0, RoomNight INT DEFAULT 0, GuestNight INT DEFAULT 0, FocRoomNights INT DEFAULT 0, HuRoomNights INT DEFAULT 0,
        RoomRevenue DECIMAL(18,2) DEFAULT 0, FbRevenue DECIMAL(18,2) DEFAULT 0, OtherRevenue DECIMAL(18,2) DEFAULT 0,
        ExtraBedRevenue DECIMAL(18,2) DEFAULT 0, ChildSurchargeRevenue DECIMAL(18,2) DEFAULT 0,
        RoomType VARCHAR(100), Nationality VARCHAR(50)
    );

    IF v_from_past <= v_to_past THEN
        INSERT INTO tmp_company_detail_items
        SELECT b.id, CONCAT(v_prefix, b.id), COALESCE(NULLIF(b.external_booking_code, ''), ''),
               COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'), COALESCE(NULLIF(b.booking_name, ''), 'Khách lẻ'),
               COALESCE(NULLIF(m.code, ''), ''), COALESCE(NULLIF(cs.code, ''), ''), DATE(b.booking_date),
               DATE(b.arrival_date), DATE(b.departure_date), GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0),
               COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 1),
               DATE(COALESCE(rnb.date, sb.Date)), COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN COALESCE(NULLIF(COALESCE(rnb.adult, 0) + COALESCE(rnb.child, 0), 0), COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0), 0) ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND (COALESCE(rnb.rate_code, '') = 'FOC' OR COALESCE(rnb.rate, 0) = 0) THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.rate_code, '') = 'HU' THEN 1 ELSE 0 END,
               CASE WHEN sb.ServiceId = 'RM' THEN COALESCE(sb.Amount, 0) ELSE 0 END, 0,
               CASE WHEN FIND_IN_SET(sb.ServiceId, REPLACE(v_er_services, ' ', '')) > 0 THEN COALESCE(sb.Amount, 0) ELSE 0 END,
               CASE WHEN sb.ServiceId = 'EB' THEN COALESCE(sb.Amount, 0) ELSE 0 END,
               CASE WHEN FIND_IN_SET(sb.ServiceId, REPLACE(v_er_services, ' ', '')) > 0 THEN COALESCE(sb.Amount, 0) ELSE 0 END,
               COALESCE((SELECT rc.code FROM booking_rooms br2 INNER JOIN rooms r2 ON r2.room_number = br2.room_number LEFT JOIN room_classes rc ON rc.id = br2.room_class_id WHERE br2.booking_id = b.id AND br2.status NOT IN (3, 100) AND COALESCE(r2.is_internal, 0) = 0 AND br2.room_number NOT LIKE '0%' ORDER BY br2.id LIMIT 1), 'STD'),
               COALESCE((SELECT g.nationality_code FROM booking_room_guests brg INNER JOIN guests g ON g.id = brg.guest_id INNER JOIN booking_rooms br3 ON br3.id = brg.booking_room_id INNER JOIN rooms r3 ON r3.room_number = br3.room_number WHERE br3.booking_id = b.id AND COALESCE(r3.is_internal, 0) = 0 AND br3.room_number NOT LIKE '0%' ORDER BY brg.is_primary DESC, brg.id LIMIT 1), 'VNM')
          FROM service_bills sb
          LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
          LEFT JOIN bookings b ON b.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))
          LEFT JOIN companies c ON c.id = COALESCE(b.company_id, sb.CompanyId2)
          LEFT JOIN markets m ON m.id = b.market_id
          LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
          LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
          LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
          LEFT JOIN rooms r ON r.room_number = br.room_number
         WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
           AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN v_from_past AND v_to_past
            AND b.id IS NOT NULL AND b.deleted_at IS NULL AND b.status NOT IN (3, 4)
           AND COALESCE(rs.is_availability, 1) = 1
           AND (br.room_number IS NULL OR (COALESCE(r.is_internal, 0) = 0 AND br.room_number NOT LIKE '0%'))
           AND EXISTS (SELECT 1 FROM booking_rooms brp INNER JOIN rooms rp ON rp.room_number = brp.room_number WHERE brp.booking_id = b.id AND brp.status NOT IN (3, 100) AND COALESCE(rp.is_internal, 0) = 0 AND brp.room_number NOT LIKE '0%')
           AND (sb.ServiceId IN ('RM', 'EB') OR FIND_IN_SET(sb.ServiceId, REPLACE(v_er_services, ' ', '')) > 0)
           AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
           AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
           AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
           AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
           AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number WHERE bra.booking_id = b.id AND rra.area = p_area));
    END IF;

    IF v_from_future <= v_to_future THEN
        INSERT INTO tmp_company_detail_items
        SELECT b.id, CONCAT(v_prefix, b.id), COALESCE(NULLIF(b.external_booking_code, ''), ''),
               COALESCE(c.code, 'WALKIN'), COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ'), COALESCE(NULLIF(b.booking_name, ''), 'Khách lẻ'),
               COALESCE(NULLIF(m.code, ''), ''), COALESCE(NULLIF(cs.code, ''), ''), DATE(b.booking_date),
               DATE(b.arrival_date), DATE(b.departure_date), GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0),
               COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 1), tc.cal_date,
               COALESCE((SELECT COUNT(*) FROM booking_rooms br0 INNER JOIN rooms r0 ON r0.room_number = br0.room_number WHERE br0.booking_id = b.id AND br0.status NOT IN (3, 100) AND COALESCE(r0.is_internal, 0) = 0 AND br0.room_number NOT LIKE '0%'), 0),
               1, COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0),
               CASE WHEN COALESCE(br.rate_code, '') = 'FOC' OR COALESCE(br.rate, 0) = 0 THEN 1 ELSE 0 END,
               CASE WHEN COALESCE(br.rate_code, '') = 'HU' THEN 1 ELSE 0 END,
               COALESCE(br.rate, 0), 0, 0, 0, 0,
               COALESCE((SELECT rc.code FROM room_classes rc WHERE rc.id = br.room_class_id LIMIT 1), 'STD'),
               COALESCE((SELECT g.nationality_code FROM booking_room_guests brg INNER JOIN guests g ON g.id = brg.guest_id WHERE brg.booking_room_id = br.id ORDER BY brg.is_primary DESC, brg.id LIMIT 1), 'VNM')
          FROM booking_rooms br
          INNER JOIN bookings b ON b.id = br.booking_id
          INNER JOIN tmp_company_detail_calendar tc ON tc.cal_date >= v_from_future AND tc.cal_date <= v_to_future
                                                     AND tc.cal_date >= br.arrival_date AND tc.cal_date < br.departure_date
          LEFT JOIN companies c ON c.id = b.company_id
          LEFT JOIN markets m ON m.id = b.market_id
          LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
          LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
          INNER JOIN rooms r ON r.room_number = br.room_number
         WHERE b.deleted_at IS NULL AND b.status NOT IN (3, 4) AND br.status NOT IN (3, 100)
           AND COALESCE(rs.is_availability, 1) = 1
           AND COALESCE(r.is_internal, 0) = 0 AND br.room_number NOT LIKE '0%'
           AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
           AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
           AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
           AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
           AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM rooms rra WHERE rra.room_number = br.room_number AND rra.area = p_area));
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_days;
    CREATE TEMPORARY TABLE tmp_company_detail_days AS
    SELECT BookingId, BookingCode, ReferenceCode, CompanyCode, CompanyName, GuestName, MarketSegment, SourceCode, BookingDate,
           ArrivalDate, DepartureDate, NoOfNight, NoOfRoom, ActivityDate, MAX(RoomQty) AS RoomQty, SUM(RoomNight) AS RoomNight,
           SUM(GuestNight) AS GuestNight, SUM(FocRoomNights) AS FocRoomNights, SUM(HuRoomNights) AS HuRoomNights,
           SUM(RoomRevenue) AS RoomRevenue, SUM(FbRevenue) AS FbRevenue, SUM(OtherRevenue) AS OtherRevenue,
           SUM(ExtraBedRevenue) AS ExtraBedRevenue, SUM(ChildSurchargeRevenue) AS ChildSurchargeRevenue, RoomType, Nationality
      FROM tmp_company_detail_items
      GROUP BY BookingId, BookingCode, ReferenceCode, CompanyCode, CompanyName, GuestName, MarketSegment, SourceCode, BookingDate,
               ArrivalDate, DepartureDate, NoOfNight, NoOfRoom, ActivityDate, RoomType, Nationality;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_rows;
    CREATE TEMPORARY TABLE tmp_company_detail_rows AS
    SELECT BookingId, MAX(BookingCode) AS BookingCode, MAX(ReferenceCode) AS ReferenceCode, CompanyCode, CompanyName, MAX(GuestName) AS GuestName,
           MAX(MarketSegment) AS MarketSegment, MAX(SourceCode) AS SourceCode, MAX(BookingDate) AS BookingDate,
           MIN(ArrivalDate) AS ArrivalDate, MAX(DepartureDate) AS DepartureDate, MAX(NoOfNight) AS NoOfNight, MAX(NoOfRoom) AS NoOfRoom,
           SUM(RoomNight) AS RoomNight, SUM(GuestNight) AS GuestNight, SUM(FocRoomNights) AS FocRoomNights, SUM(HuRoomNights) AS HuRoomNights,
           SUM(RoomRevenue) AS RoomRevenue, SUM(FbRevenue) AS FbRevenue, SUM(OtherRevenue) AS OtherRevenue,
           SUM(ExtraBedRevenue) AS ExtraBedRevenue, SUM(ChildSurchargeRevenue) AS ChildSurchargeRevenue,
           SUM(RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue) AS TotalRevenue, MAX(RoomType) AS RoomType, MAX(Nationality) AS Nationality
      FROM tmp_company_detail_days
      GROUP BY BookingId, CompanyCode, CompanyName
     HAVING SUM(RoomNight) <> 0 OR SUM(RoomRevenue + ExtraBedRevenue + ChildSurchargeRevenue) <> 0;

    SELECT BookingCode, ReferenceCode, CompanyName, GuestName, MarketSegment, SourceCode,
           DATE_FORMAT(BookingDate, '%d/%m/%Y') AS BookingDate,
           DATE_FORMAT(ArrivalDate, '%d/%m/%Y') AS ArrivalDate, DATE_FORMAT(DepartureDate, '%d/%m/%Y') AS DepartureDate,
           CAST(NoOfNight AS SIGNED) AS NoOfNight, CAST(NoOfRoom AS SIGNED) AS NoOfRoom,
           CAST(RoomNight AS SIGNED) AS RoomNight, CAST(GuestNight AS SIGNED) AS GuestNight,
           ROUND(CASE WHEN RoomNight = 0 THEN 0 ELSE RoomRevenue / RoomNight END, 0) AS AverageRate,
           ROUND(CASE WHEN RoomNight - FocRoomNights - HuRoomNights = 0 THEN 0 ELSE RoomRevenue / (RoomNight - FocRoomNights - HuRoomNights) END, 0) AS AverageRateOriginal,
           ROUND(RoomRevenue, 0) AS RoomRevenue, ROUND(FbRevenue, 0) AS FbRevenue, ROUND(OtherRevenue, 0) AS OtherRevenue,
           ROUND(TotalRevenue, 0) AS TotalRevenue, RoomType, Nationality, CompanyCode,
           ROW_NUMBER() OVER (PARTITION BY CompanyCode ORDER BY ArrivalDate, CONCAT(v_prefix, BookingId)) AS RowNumber,
           ROUND(ExtraBedRevenue, 0) AS ExtraBedRevenue, ROUND(ChildSurchargeRevenue, 0) AS ChildSurchargeRevenue
      FROM tmp_company_detail_rows
     ORDER BY CompanyName, ArrivalDate, BookingCode;

    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_rows;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_days;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_items;
    DROP TEMPORARY TABLE IF EXISTS tmp_company_detail_calendar;
END
SQL;
    }
};
