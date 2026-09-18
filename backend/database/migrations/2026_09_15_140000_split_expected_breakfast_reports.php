<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_1 = 'EXPECTED_BREAKFAST_1';
    private const REPORT_1 = 'EXPECTED_BREAKFAST_1';
    private const TEMPLATE_1 = 'EXPECTED_BREAKFAST_1_STANDARD';

    private const SOURCE_2 = 'EXPECTED_BREAKFAST_2';
    private const REPORT_2 = 'EXPECTED_BREAKFAST_2';
    private const TEMPLATE_2 = 'EXPECTED_BREAKFAST_2_STANDARD';

    public function up(): void
    {
        $connections = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];

        $summaryProvider = require database_path('report_templates/expected_breakfast_summary_reference.php');
        $detailProvider = require database_path('report_templates/expected_breakfast_detail_reference.php');

        $summaryDef = $summaryProvider->definition();
        $detailDef = $detailProvider->definition();

        $now = now();

        $parameterSchema = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_show_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 4, 'required' => false],
            ['name' => 'p_sort_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(30)', 'position' => 5, 'required' => true],
            ['name' => 'p_sort_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(4)', 'position' => 6, 'required' => true],
            ['name' => 'p_late_checkin', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
            ['name' => 'p_group_by_booking', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 8, 'required' => false],
        ];

        $fieldSchema1 = [
            ['name' => 'STT', 'type' => 'integer', 'nullable' => true],
            ['name' => 'DateGroup', 'type' => 'string', 'nullable' => true],
            ['name' => 'Date', 'type' => 'string', 'nullable' => true],
            ['name' => 'BookingCode', 'type' => 'string', 'nullable' => true],
            ['name' => 'Booking', 'type' => 'string', 'nullable' => true],
            ['name' => 'BookingId', 'type' => 'integer', 'nullable' => true],
            ['name' => 'BookingName', 'type' => 'string', 'nullable' => true],
            ['name' => 'Room', 'type' => 'string', 'nullable' => true],
            ['name' => 'GuestName', 'type' => 'string', 'nullable' => true],
            ['name' => 'CompanyName', 'type' => 'string', 'nullable' => true],
            ['name' => 'Nationality', 'type' => 'string', 'nullable' => true],
            ['name' => 'Adults', 'type' => 'integer', 'nullable' => true],
            ['name' => 'Adult', 'type' => 'integer', 'nullable' => true],
            ['name' => 'Children', 'type' => 'integer', 'nullable' => true],
            ['name' => 'Child', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildrenNK', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildMP', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildrenNoBreakfast', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildKAS', 'type' => 'integer', 'nullable' => true],
            ['name' => 'TotalPax', 'type' => 'integer', 'nullable' => true],
            ['name' => 'Description', 'type' => 'string', 'nullable' => true],
            ['name' => 'Note', 'type' => 'string', 'nullable' => true],
            ['name' => 'RoomType', 'type' => 'string', 'nullable' => true],
            ['name' => 'IsBreakfast', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ArrivalDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'DepartureDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'DetailRoom', 'type' => 'string', 'nullable' => true],
            ['name' => 'DateTotalAdults', 'type' => 'integer', 'nullable' => true],
            ['name' => 'DateTotalChildren', 'type' => 'integer', 'nullable' => true],
            ['name' => 'DateTotalChildrenNK', 'type' => 'integer', 'nullable' => true],
            ['name' => 'DateTotalPax', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalAdults', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalChildren', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalChildrenNK', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalPax', 'type' => 'integer', 'nullable' => true],
        ];

        $fieldSchema2 = [
            ['name' => 'STT', 'type' => 'integer', 'nullable' => true],
            ['name' => 'DateGroup', 'type' => 'string', 'nullable' => true],
            ['name' => 'Date', 'type' => 'string', 'nullable' => true],
            ['name' => 'Room', 'type' => 'string', 'nullable' => true],
            ['name' => 'GuestName', 'type' => 'string', 'nullable' => true],
            ['name' => 'Nationality', 'type' => 'string', 'nullable' => true],
            ['name' => 'ArrivalDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'DepartureDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'Note', 'type' => 'string', 'nullable' => true],
            ['name' => 'RoomType', 'type' => 'string', 'nullable' => true],
            ['name' => 'DetailRoom', 'type' => 'string', 'nullable' => true],
            ['name' => 'BookingId', 'type' => 'integer', 'nullable' => true],
            ['name' => 'BookingName', 'type' => 'string', 'nullable' => true],
            ['name' => 'Adults', 'type' => 'integer', 'nullable' => true],
            ['name' => 'Children', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildrenNK', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ChildrenNoBreakfast', 'type' => 'integer', 'nullable' => true],
            ['name' => 'TotalPax', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalAdults', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalChildren', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalChildrenNK', 'type' => 'integer', 'nullable' => true],
            ['name' => 'ReportTotalPax', 'type' => 'integer', 'nullable' => true],
        ];

        $uiSchema1 = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_show_type',
                'label' => 'Xem theo',
                'control' => 'select',
                'default' => 1,
                'required' => true,
                'options' => [
                    ['label' => 'Phòng có ăn sáng', 'value' => 1],
                    ['label' => 'Phòng không ăn sáng', 'value' => 2],
                    ['label' => 'Tất cả', 'value' => 0],
                ],
            ],
            [
                'name' => 'p_user',
                'label' => 'Người dùng',
                'control' => 'select',
                'default' => '',
                'required' => false,
                'placeholder' => 'Select Value',
                'options_source' => 'users',
                'options' => [],
            ],
            [
                'name' => 'p_sort_by',
                'label' => 'Sắp xếp theo',
                'control' => 'select',
                'default' => 'Room',
                'required' => true,
                'options' => [
                    ['label' => 'Room', 'value' => 'Room'],
                    ['label' => 'Ngày đến', 'value' => 'ArrivalDate'],
                    ['label' => 'Ngày đi', 'value' => 'DepartureDate'],
                ],
            ],
            [
                'name' => 'p_sort_type',
                'label' => 'Thứ tự',
                'control' => 'select',
                'default' => 'ASC',
                'required' => true,
                'options' => [
                    ['label' => 'ASC', 'value' => 'ASC'],
                    ['label' => 'DESC', 'value' => 'DESC'],
                ],
            ],
            [
                'name' => 'p_late_checkin',
                'label' => 'Nhận phòng trễ',
                'control' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'p_group_by_booking',
                'label' => 'Đăng ký theo nhóm',
                'control' => 'checkbox',
                'default' => false,
                'required' => false,
            ],
        ];

        $uiSchema2 = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_show_type',
                'label' => 'Xem theo',
                'control' => 'select',
                'default' => 1,
                'required' => true,
                'options' => [
                    ['label' => 'Phòng có ăn sáng', 'value' => 1],
                    ['label' => 'Phòng không ăn sáng', 'value' => 2],
                    ['label' => 'Tất cả', 'value' => 0],
                ],
            ],
            [
                'name' => 'p_user',
                'label' => 'Người dùng',
                'control' => 'select',
                'default' => '',
                'required' => false,
                'placeholder' => 'Select Value',
                'options_source' => 'users',
                'options' => [],
            ],
            [
                'name' => 'p_sort_by',
                'label' => 'Sắp xếp theo',
                'control' => 'select',
                'default' => 'Room',
                'required' => true,
                'options' => [
                    ['label' => 'Room', 'value' => 'Room'],
                    ['label' => 'Ngày đến', 'value' => 'ArrivalDate'],
                    ['label' => 'Ngày đi', 'value' => 'DepartureDate'],
                ],
            ],
            [
                'name' => 'p_sort_type',
                'label' => 'Thứ tự',
                'control' => 'select',
                'default' => 'ASC',
                'required' => true,
                'options' => [
                    ['label' => 'ASC', 'value' => 'ASC'],
                    ['label' => 'DESC', 'value' => 'DESC'],
                ],
            ],
            [
                'name' => 'p_late_checkin',
                'label' => 'Nhận phòng trễ',
                'control' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'p_group_by_booking',
                'label' => 'Đăng ký theo nhóm',
                'control' => 'checkbox',
                'default' => false,
                'required' => false,
            ],
        ];

        $proc1Sql = $this->buildProcedureSql('rpt_expected_breakfast_1', 0);
        $proc2Sql = $this->buildProcedureSql('rpt_expected_breakfast_2', 1);

        foreach ($connections as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $dbName = DB::connection($conn)->getDatabaseName();

                // 1. Create Stored Procedures on Connection
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast_1`');
                DB::connection($conn)->unprepared($proc1Sql);

                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast_2`');
                DB::connection($conn)->unprepared($proc2Sql);

                // Clean old single definition and data source
                $oldReportId = DB::connection($conn)->table('report_definitions')->where('code', 'EXPECTED_BREAKFAST')->value('id');
                if ($oldReportId) {
                    DB::connection($conn)->table('report_definition_template')->where('report_definition_id', $oldReportId)->delete();
                    DB::connection($conn)->table('report_definitions')->where('id', $oldReportId)->delete();
                }
                DB::connection($conn)->table('report_data_sources')->where('code', 'EXPECTED_BREAKFAST')->delete();

                // 2. Data Source 1
                DB::connection($conn)->table('report_data_sources')->updateOrInsert(
                    ['code' => self::SOURCE_1],
                    [
                        'name' => 'Dữ liệu báo cáo dự kiến khách ăn sáng 1',
                        'description' => 'MySQL chuyển đổi từ ProVista sp_035.',
                        'source_type' => 'procedure',
                        'schema_name' => $dbName,
                        'object_name' => 'rpt_expected_breakfast_1',
                        'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE),
                        'field_schema' => json_encode($fieldSchema1, JSON_UNESCAPED_UNICODE),
                        'sample_parameters' => json_encode([
                            'p_from_date' => date('Y-m-d'),
                            'p_to_date' => date('Y-m-d'),
                            'p_show_type' => 1,
                            'p_user' => '',
                            'p_sort_by' => 'Room',
                            'p_sort_type' => 'ASC',
                            'p_late_checkin' => 1,
                            'p_group_by_booking' => 0,
                        ]),
                        'max_rows' => 5000,
                        'is_active' => true,
                        'last_discovered_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $source1Id = DB::connection($conn)->table('report_data_sources')->where('code', self::SOURCE_1)->value('id');

                // 3. Data Source 2
                DB::connection($conn)->table('report_data_sources')->updateOrInsert(
                    ['code' => self::SOURCE_2],
                    [
                        'name' => 'Dữ liệu báo cáo dự kiến khách ăn sáng 2',
                        'description' => 'MySQL chuyển đổi từ ProVista sp_032.',
                        'source_type' => 'procedure',
                        'schema_name' => $dbName,
                        'object_name' => 'rpt_expected_breakfast_2',
                        'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE),
                        'field_schema' => json_encode($fieldSchema2, JSON_UNESCAPED_UNICODE),
                        'sample_parameters' => json_encode([
                            'p_from_date' => date('Y-m-d'),
                            'p_to_date' => date('Y-m-d'),
                            'p_show_type' => 1,
                            'p_user' => '',
                            'p_sort_by' => 'Room',
                            'p_sort_type' => 'ASC',
                            'p_late_checkin' => 1,
                            'p_group_by_booking' => 0,
                        ]),
                        'max_rows' => 5000,
                        'is_active' => true,
                        'last_discovered_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $source2Id = DB::connection($conn)->table('report_data_sources')->where('code', self::SOURCE_2)->value('id');

                // 4. Templates
                DB::connection($conn)->table('templates')->updateOrInsert(
                    ['report' => self::TEMPLATE_1],
                    [
                        'name' => 'Báo cáo dự kiến khách ăn sáng 1 - Mẫu chuẩn',
                        'page_size' => $summaryDef['page_size'] ?? 'A4',
                        'page_orientation' => $summaryDef['page_orientation'] ?? 'portrait',
                        'margin_top' => $summaryDef['margin_top'] ?? 6,
                        'margin_bottom' => $summaryDef['margin_bottom'] ?? 6,
                        'margin_left' => $summaryDef['margin_left'] ?? 5,
                        'margin_right' => $summaryDef['margin_right'] ?? 5,
                        'version' => '1.0',
                        'content_html' => $summaryDef['content_html'],
                        'content_json' => json_encode($summaryDef['content_json'], JSON_UNESCAPED_UNICODE),
                        'css' => $summaryDef['css'],
                        'report_data_source_id' => $source1Id,
                        'is_default' => true,
                        'updated_at' => $now,
                    ]
                );
                $template1Id = DB::connection($conn)->table('templates')->where('report', self::TEMPLATE_1)->value('id');

                DB::connection($conn)->table('templates')->updateOrInsert(
                    ['report' => self::TEMPLATE_2],
                    [
                        'name' => 'Báo cáo dự kiến khách ăn sáng 2 - Mẫu chuẩn',
                        'page_size' => $detailDef['page_size'] ?? 'A4',
                        'page_orientation' => $detailDef['page_orientation'] ?? 'portrait',
                        'margin_top' => $detailDef['margin_top'] ?? 6,
                        'margin_bottom' => $detailDef['margin_bottom'] ?? 6,
                        'margin_left' => $detailDef['margin_left'] ?? 5,
                        'margin_right' => $detailDef['margin_right'] ?? 5,
                        'version' => '1.0',
                        'content_html' => $detailDef['content_html'],
                        'content_json' => json_encode($detailDef['content_json'], JSON_UNESCAPED_UNICODE),
                        'css' => $detailDef['css'],
                        'report_data_source_id' => $source2Id,
                        'is_default' => true,
                        'updated_at' => $now,
                    ]
                );
                $template2Id = DB::connection($conn)->table('templates')->where('report', self::TEMPLATE_2)->value('id');

                // Delete old temporary templates
                DB::connection($conn)->table('templates')->whereIn('report', [
                    'EXPECTED_BREAKFAST_SUMMARY_STANDARD',
                    'EXPECTED_BREAKFAST_DETAIL_STANDARD',
                ])->delete();

                // 5. Report Definitions
                DB::connection($conn)->table('report_definitions')->updateOrInsert(
                    ['code' => self::REPORT_1],
                    [
                        'name' => 'Báo cáo dự kiến khách ăn sáng 1',
                        'group' => 'Báo cáo phòng',
                        'description' => 'Dự kiến khách ăn sáng xem tổng hợp theo phòng (ProVista sp_035).',
                        'report_data_source_id' => $source1Id,
                        'parameter_ui_schema' => json_encode($uiSchema1, JSON_UNESCAPED_UNICODE),
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
                $report1Id = DB::connection($conn)->table('report_definitions')->where('code', self::REPORT_1)->value('id');

                DB::connection($conn)->table('report_definitions')->updateOrInsert(
                    ['code' => self::REPORT_2],
                    [
                        'name' => 'Báo cáo dự kiến khách ăn sáng 2',
                        'group' => 'Báo cáo phòng',
                        'description' => 'Dự kiến khách ăn sáng xem theo chi tiết khách trong phòng (ProVista sp_032).',
                        'report_data_source_id' => $source2Id,
                        'parameter_ui_schema' => json_encode($uiSchema2, JSON_UNESCAPED_UNICODE),
                        'sort_order' => 35,
                        'is_active' => true,
                        'show_in_menu' => true,
                        'menu_locations' => json_encode(['reservation', 'frontdesk']),
                        'menu_top_order' => 20,
                        'menu_group_order' => 10,
                        'menu_item_order' => 35,
                        'updated_at' => $now,
                    ]
                );
                $report2Id = DB::connection($conn)->table('report_definitions')->where('code', self::REPORT_2)->value('id');

                // 6. Pivot links
                if ($report1Id && $template1Id) {
                    DB::connection($conn)->table('report_definition_template')->where('report_definition_id', $report1Id)->delete();
                    DB::connection($conn)->table('report_definition_template')->insert([
                        'report_definition_id' => $report1Id,
                        'template_id' => $template1Id,
                        'is_default' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($report2Id && $template2Id) {
                    DB::connection($conn)->table('report_definition_template')->where('report_definition_id', $report2Id)->delete();
                    DB::connection($conn)->table('report_definition_template')->insert([
                        'report_definition_id' => $report2Id,
                        'template_id' => $template2Id,
                        'is_default' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } catch (\Throwable $e) {
                echo "Conn $conn error: " . $e->getMessage() . "\n";
            }
        }
    }

    public function down(): void
    {
        $connections = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];

        foreach ($connections as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $rep1 = DB::connection($conn)->table('report_definitions')->where('code', self::REPORT_1)->value('id');
                $rep2 = DB::connection($conn)->table('report_definitions')->where('code', self::REPORT_2)->value('id');

                if ($rep1) {
                    DB::connection($conn)->table('report_definition_template')->where('report_definition_id', $rep1)->delete();
                    DB::connection($conn)->table('report_definitions')->where('id', $rep1)->delete();
                }
                if ($rep2) {
                    DB::connection($conn)->table('report_definition_template')->where('report_definition_id', $rep2)->delete();
                    DB::connection($conn)->table('report_definitions')->where('id', $rep2)->delete();
                }

                DB::connection($conn)->table('templates')->whereIn('report', [self::TEMPLATE_1, self::TEMPLATE_2])->delete();
                DB::connection($conn)->table('report_data_sources')->whereIn('code', [self::SOURCE_1, self::SOURCE_2])->delete();
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast_1`');
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast_2`');
            } catch (\Throwable) {
                // Ignore
            }
        }
    }

    private function buildProcedureSql(string $procedureName, int $isDetailMode): string
    {
        $selectQuery = $isDetailMode === 0
            ? $this->summarySelectSql()
            : $this->detailSelectSql();

        return <<<SQL
CREATE PROCEDURE `{$procedureName}`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_show_type TINYINT,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(30),
    IN p_sort_type VARCHAR(4),
    IN p_late_checkin TINYINT,
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    SET p_from_date = COALESCE(p_from_date, CURDATE());
    SET p_to_date = GREATEST(p_from_date, COALESCE(p_to_date, p_from_date));
    SET p_show_type = COALESCE(p_show_type, 1);
    SET p_sort_by = COALESCE(NULLIF(p_sort_by, ''), 'Room');
    SET p_sort_type = UPPER(COALESCE(NULLIF(p_sort_type, ''), 'ASC'));
    SET p_late_checkin = COALESCE(p_late_checkin, 1);
    SET p_group_by_booking = COALESCE(p_group_by_booking, 0);

    -- 1. Date calendar series
    DROP TEMPORARY TABLE IF EXISTS tmp_dates;
    CREATE TEMPORARY TABLE tmp_dates (
        report_date DATE PRIMARY KEY
    );

    SET @cur_d = p_from_date;
    WHILE @cur_d <= p_to_date DO
        INSERT INTO tmp_dates (report_date) VALUES (@cur_d);
        SET @cur_d = DATE_ADD(@cur_d, INTERVAL 1 DAY);
    END WHILE;

    -- 2. Rooms active on each date
    DROP TEMPORARY TABLE IF EXISTS tmp_active_rooms;
    CREATE TEMPORARY TABLE tmp_active_rooms (
        report_date DATE,
        booking_room_id VARCHAR(50),
        booking_id BIGINT,
        room_number VARCHAR(20),
        room_type_name VARCHAR(50),
        is_late_checkin TINYINT,
        arrival_date DATE,
        departure_date DATE,
        arrival_time TIME,
        breakfast TINYINT,
        adults INT,
        children_qty INT,
        babies INT,
        note TEXT,
        booking_note TEXT,
        booking_name VARCHAR(255),
        company_name VARCHAR(255),
        created_by VARCHAR(100),
        INDEX (booking_room_id, report_date),
        INDEX (report_date)
    );

    -- 2a. In-house regular rooms (PHÒNG Ở THẬT)
    INSERT INTO tmp_active_rooms (
        report_date, booking_room_id, booking_id, room_number, room_type_name,
        is_late_checkin, arrival_date, departure_date, arrival_time,
        breakfast, adults, children_qty, babies, note, booking_note,
        booking_name, company_name, created_by
    )
    SELECT DISTINCT
        d.report_date,
        br.id AS booking_room_id,
        b.id AS booking_id,
        br.room_number,
        'PHÒNG Ở THẬT' AS room_type_name,
        0 AS is_late_checkin,
        br.arrival_date,
        br.departure_date,
        br.arrival_time,
        br.breakfast,
        br.adults,
        br.children_qty,
        br.babies,
        br.note,
        b.note AS booking_note,
        b.booking_name,
        c.name AS company_name,
        br.created_by
    FROM tmp_dates d
    CROSS JOIN booking_rooms br
    INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
    LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
    LEFT JOIN rooms r ON r.room_number = br.room_number
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.status <> 3
    WHERE br.deleted_at IS NULL
      AND br.status IN (0, 1, 2, 100)
      AND COALESCE(rs.is_availability, 1) = 1
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
      AND COALESCE(r.is_internal, 0) = 0
      AND (
          (brg.id IS NOT NULL AND (
              (d.report_date BETWEEN DATE_ADD(COALESCE(brg.actual_arrival_date, br.arrival_date), INTERVAL 1 DAY) AND COALESCE(brg.actual_checkout_date, br.departure_date))
              OR (COALESCE(brg.actual_arrival_date, br.arrival_date) = COALESCE(brg.actual_checkout_date, br.departure_date) AND d.report_date = COALESCE(brg.actual_arrival_date, br.arrival_date))
              OR (d.report_date = COALESCE(brg.actual_arrival_date, br.arrival_date) AND br.arrival_time <= '00:01')
          ))
          OR (brg.id IS NULL AND (
              (d.report_date BETWEEN DATE_ADD(br.arrival_date, INTERVAL 1 DAY) AND br.departure_date)
              OR (br.arrival_date = br.departure_date AND d.report_date = br.arrival_date)
              OR (d.report_date = br.arrival_date AND br.arrival_time <= '00:01')
          ))
      )
      AND br.id NOT IN (
          SELECT lc.booking_room_id
          FROM late_checkins lc
          WHERE lc.actual_arrival_date = d.report_date
      );

    -- 2b. Late check-in rooms (PHÒNG LATE CHECK IN)
    IF p_late_checkin = 1 THEN
        INSERT INTO tmp_active_rooms (
            report_date, booking_room_id, booking_id, room_number, room_type_name,
            is_late_checkin, arrival_date, departure_date, arrival_time,
            breakfast, adults, children_qty, babies, note, booking_note,
            booking_name, company_name, created_by
        )
        SELECT DISTINCT
            d.report_date,
            br.id AS booking_room_id,
            b.id AS booking_id,
            br.room_number,
            'PHÒNG LATE CHECK IN' AS room_type_name,
            1 AS is_late_checkin,
            br.arrival_date,
            br.departure_date,
            br.arrival_time,
            br.breakfast,
            br.adults,
            br.children_qty,
            br.babies,
            br.note,
            b.note AS booking_note,
            b.booking_name,
            c.name AS company_name,
            br.created_by
        FROM tmp_dates d
        CROSS JOIN booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
        LEFT JOIN rooms r ON r.room_number = br.room_number
        LEFT JOIN companies c ON c.id = b.company_id
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2, 100)
          AND COALESCE(rs.is_availability, 1) = 1
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND COALESCE(r.is_internal, 0) = 0
          AND (br.arrival_date < d.report_date OR br.arrival_time <= '05:30' OR br.arrival_time <= '09:30')
          AND (
              br.id IN (
                  SELECT lc.booking_room_id
                  FROM late_checkins lc
                  WHERE lc.actual_arrival_date = d.report_date
              )
              OR (
                  br.arrival_date = d.report_date
                  AND (br.arrival_time <= '05:30' OR br.arrival_time <= '09:30')
              )
          );
    END IF;

    -- 3. Room-level summary table
    DROP TEMPORARY TABLE IF EXISTS tmp_room_summary;
    CREATE TEMPORARY TABLE tmp_room_summary (
        report_date DATE,
        booking_room_id VARCHAR(50),
        booking_id BIGINT,
        booking_code VARCHAR(50),
        booking_name VARCHAR(255),
        company_name VARCHAR(255),
        room_number VARCHAR(20),
        room_type_name VARCHAR(50),
        is_late_checkin TINYINT,
        guest_name VARCHAR(255),
        nationality VARCHAR(100),
        arrival_date DATE,
        departure_date DATE,
        adults INT,
        children INT,
        children_nk INT,
        children_no_bf INT,
        total_pax INT,
        extra_bf_desc TEXT,
        note TEXT,
        is_breakfast TINYINT,
        detail_room TEXT,
        INDEX (booking_room_id, report_date)
    );

    INSERT INTO tmp_room_summary
    SELECT
        ar.report_date,
        ar.booking_room_id,
        ar.booking_id,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), ar.booking_id) AS booking_code,
        COALESCE(ar.booking_name, '') AS booking_name,
        COALESCE(ar.company_name, '') AS company_name,
        COALESCE(ar.room_number, '') AS room_number,
        ar.room_type_name,
        ar.is_late_checkin,
        COALESCE(
            (SELECT TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name))
             FROM booking_room_guests brg
             INNER JOIN guests g ON g.id = brg.guest_id
             WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3
             ORDER BY brg.is_primary DESC, brg.id ASC LIMIT 1),
            ''
        ) AS guest_name,
        COALESCE(
            (SELECT COALESCE(n.nationality_name, g.nationality_code)
             FROM booking_room_guests brg
             INNER JOIN guests g ON g.id = brg.guest_id
             LEFT JOIN nationalities n ON n.nationality_code = g.nationality_code
             WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3
             ORDER BY brg.is_primary DESC, brg.id ASC LIMIT 1),
            ''
        ) AS nationality,
        ar.arrival_date,
        ar.departure_date,
        COALESCE(
            NULLIF((SELECT COUNT(brg.id)
                    FROM booking_room_guests brg
                    WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3), 0),
            ar.adults,
            1
        ) AS adults,
        COALESCE(
            (SELECT COUNT(bc.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 1),
            ar.children_qty,
            0
        ) AS children,
        COALESCE(
            (SELECT COUNT(bc.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 2),
            0
        ) AS children_nk,
        COALESCE(
            (SELECT COUNT(bc.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 0),
            0
        ) AS children_no_bf,
        0 AS total_pax,
        COALESCE(
            (SELECT GROUP_CONCAT(CONCAT('* ', COALESCE(sb.DescriptionServive, ''), ' - ', COALESCE(sb.Quantity, 1)) SEPARATOR '\n')
             FROM service_bills sb
             WHERE sb.RentalRoomId1 = ar.booking_room_id
               AND DATE(sb.Date) = ar.report_date
               AND sb.status = 1),
            ''
        ) AS extra_bf_desc,
        COALESCE(ar.note, ar.booking_note, '') AS note,
        CASE
            WHEN ar.breakfast = 1 THEN 1
            WHEN (SELECT COUNT(*) FROM booking_room_services brs
                  WHERE brs.booking_room_id = ar.booking_room_id
                    AND DATE(brs.service_date) = DATE_SUB(ar.report_date, INTERVAL 1 DAY)
                    AND (brs.service_code = 'BF' OR brs.service_code IN ('AL', 'BD', 'BE', 'BU'))) > 0 THEN 1
            WHEN (SELECT COUNT(*) FROM booking_children bc
                  INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
                  WHERE bc.booking_room_id = ar.booking_room_id
                    AND bcbd.service_date = ar.report_date
                    AND bcbd.breakfast = 1) > 0 THEN 1
            ELSE 0
        END AS is_breakfast,
        '' AS detail_room
    FROM tmp_active_rooms ar
    LEFT JOIN hotel_settings hs ON 1 = 1
    WHERE (p_user IS NULL OR p_user = '' OR ar.created_by = p_user);

    -- Calculate total_pax and detail_room
    UPDATE tmp_room_summary
    SET total_pax = adults + children + children_nk,
        detail_room = CONCAT(
            IF(room_number != '' AND room_number IS NOT NULL, CONCAT('Phòng ', room_number, ' - '), 'Phòng - '),
            'BK ', booking_id,
            IF(company_name != '' AND booking_name != '', CONCAT(' - ', company_name, '/ ', booking_name),
               IF(company_name != '', CONCAT(' - ', company_name),
                  IF(booking_name != '', CONCAT(' - ', booking_name), ''))),
            ' - Người lớn: ', adults,
            ' - Trẻ em: ', children,
            ' - Trẻ em MP: ', children_nk,
            ' - Trẻ em KAS: ', children_no_bf
        );

    -- Filter by p_show_type (1: Có ăn sáng, 2: Không ăn sáng, 0: Tất cả)
    IF p_show_type = 1 THEN
        DELETE FROM tmp_room_summary WHERE is_breakfast <> 1;
    ELSEIF p_show_type = 2 THEN
        DELETE FROM tmp_room_summary WHERE is_breakfast <> 0;
    END IF;

    {$selectQuery}
END;
SQL;
    }

    private function summarySelectSql(): string
    {
        return <<<'SQL'
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN p_group_by_booking = 1 THEN s.booking_id END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END ASC,
                s.booking_room_id ASC
        ) AS STT,
        DATE_FORMAT(s.report_date, '%d/%m/%Y') AS DateGroup,
        s.report_date AS Date,
        s.booking_code AS BookingCode,
        s.booking_code AS Booking,
        s.booking_id AS BookingId,
        s.booking_name AS BookingName,
        s.room_number AS Room,
        s.guest_name AS GuestName,
        s.company_name AS CompanyName,
        s.nationality AS Nationality,
        s.adults AS Adults,
        s.adults AS Adult,
        s.children AS Children,
        s.children AS Child,
        s.children_nk AS ChildrenNK,
        s.children_nk AS ChildMP,
        s.children_no_bf AS ChildrenNoBreakfast,
        s.children_no_bf AS ChildKAS,
        s.total_pax AS TotalPax,
        s.extra_bf_desc AS Description,
        s.note AS Note,
        s.room_type_name AS RoomType,
        s.is_breakfast AS IsBreakfast,
        DATE_FORMAT(s.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(s.departure_date, '%d/%m/%Y') AS DepartureDate,
        s.detail_room AS DetailRoom,
        SUM(s.adults) OVER (PARTITION BY s.report_date) AS DateTotalAdults,
        SUM(s.children) OVER (PARTITION BY s.report_date) AS DateTotalChildren,
        SUM(s.children_nk) OVER (PARTITION BY s.report_date) AS DateTotalChildrenNK,
        SUM(s.total_pax) OVER (PARTITION BY s.report_date) AS DateTotalPax,
        SUM(s.adults) OVER () AS ReportTotalAdults,
        SUM(s.children) OVER () AS ReportTotalChildren,
        SUM(s.children_nk) OVER () AS ReportTotalChildrenNK,
        SUM(s.total_pax) OVER () AS ReportTotalPax
    FROM tmp_room_summary s
    ORDER BY
        s.report_date ASC,
        s.room_type_name ASC,
        CASE WHEN p_group_by_booking = 1 THEN s.booking_id END ASC,
        CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END DESC,
        CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END ASC,
        CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END DESC,
        CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END ASC,
        CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END DESC,
        CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END ASC,
        s.booking_room_id ASC;
SQL;
    }

    private function detailSelectSql(): string
    {
        return <<<'SQL'
    DROP TEMPORARY TABLE IF EXISTS tmp_guest_details;
    CREATE TEMPORARY TABLE tmp_guest_details (
        report_date DATE,
        booking_room_id VARCHAR(50),
        booking_id BIGINT,
        booking_name VARCHAR(255),
        room_number VARCHAR(20),
        guest_name VARCHAR(255),
        nationality VARCHAR(100),
        arrival_date DATE,
        departure_date DATE,
        note TEXT,
        room_type_name VARCHAR(50),
        detail_room TEXT,
        adults INT,
        children INT,
        children_nk INT,
        children_no_bf INT,
        total_pax INT,
        is_child TINYINT,
        sort_order_in_room INT
    );

    -- Adult guests
    INSERT INTO tmp_guest_details
    SELECT
        s.report_date,
        s.booking_room_id,
        s.booking_id,
        s.booking_name,
        s.room_number,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS guest_name,
        COALESCE(n.nationality_name, g.nationality_code, 'Việt Nam') AS nationality,
        COALESCE(brg.actual_arrival_date, s.arrival_date) AS arrival_date,
        COALESCE(brg.actual_checkout_date, s.departure_date) AS departure_date,
        COALESCE(NULLIF(g.note, ''), s.note) AS note,
        s.room_type_name,
        s.detail_room,
        s.adults,
        s.children,
        s.children_nk,
        s.children_no_bf,
        s.total_pax,
        0 AS is_child,
        IF(brg.is_primary = 1, 0, 1) AS sort_order_in_room
    FROM tmp_room_summary s
    INNER JOIN booking_room_guests brg ON brg.booking_room_id = s.booking_room_id AND brg.status <> 3
    INNER JOIN guests g ON g.id = brg.guest_id
    LEFT JOIN nationalities n ON n.nationality_code = g.nationality_code;

    -- If room has no guests in booking_room_guests, add fallback main guest
    INSERT INTO tmp_guest_details
    SELECT
        s.report_date,
        s.booking_room_id,
        s.booking_id,
        s.booking_name,
        s.room_number,
        COALESCE(NULLIF(s.guest_name, ''), s.booking_name, 'Khách chính') AS guest_name,
        COALESCE(NULLIF(s.nationality, ''), 'Việt Nam') AS nationality,
        s.arrival_date,
        s.departure_date,
        s.note,
        s.room_type_name,
        s.detail_room,
        s.adults,
        s.children,
        s.children_nk,
        s.children_no_bf,
        s.total_pax,
        0 AS is_child,
        0 AS sort_order_in_room
    FROM tmp_room_summary s
    WHERE s.booking_room_id NOT IN (
        SELECT DISTINCT gd.booking_room_id FROM tmp_guest_details gd WHERE gd.report_date = s.report_date
    );

    -- Children rows
    INSERT INTO tmp_guest_details
    SELECT
        s.report_date,
        s.booking_room_id,
        s.booking_id,
        s.booking_name,
        s.room_number,
        CONCAT('Chd. ', COALESCE(NULLIF(bc.full_name, ''), CONCAT('Child ', bc.id))) AS guest_name,
        COALESCE(s.nationality, 'Việt Nam') AS nationality,
        s.arrival_date,
        s.departure_date,
        COALESCE(bc.note, '') AS note,
        s.room_type_name,
        s.detail_room,
        s.adults,
        s.children,
        s.children_nk,
        s.children_no_bf,
        s.total_pax,
        1 AS is_child,
        100 AS sort_order_in_room
    FROM tmp_room_summary s
    INNER JOIN booking_children bc ON bc.booking_room_id = s.booking_room_id;

    -- Return detail result
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN p_group_by_booking = 1 THEN d.booking_id END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'Room' THEN CAST(d.room_number AS UNSIGNED) END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'Room' THEN CAST(d.room_number AS UNSIGNED) END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'ArrivalDate' THEN d.arrival_date END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'ArrivalDate' THEN d.arrival_date END ASC,
                CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'DepartureDate' THEN d.departure_date END DESC,
                CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'DepartureDate' THEN d.departure_date END ASC,
                d.booking_room_id ASC,
                d.sort_order_in_room ASC,
                d.guest_name ASC
        ) AS STT,
        DATE_FORMAT(d.report_date, '%d/%m/%Y') AS DateGroup,
        d.report_date AS Date,
        d.room_number AS Room,
        d.guest_name AS GuestName,
        d.nationality AS Nationality,
        DATE_FORMAT(d.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(d.departure_date, '%d/%m/%Y') AS DepartureDate,
        d.note AS Note,
        d.room_type_name AS RoomType,
        d.detail_room AS DetailRoom,
        d.booking_id AS BookingId,
        d.booking_name AS BookingName,
        d.adults AS Adults,
        d.children AS Children,
        d.children_nk AS ChildrenNK,
        d.children_no_bf AS ChildrenNoBreakfast,
        d.total_pax AS TotalPax,
        SUM(d.adults) OVER () AS ReportTotalAdults,
        SUM(d.children) OVER () AS ReportTotalChildren,
        SUM(d.children_nk) OVER () AS ReportTotalChildrenNK,
        SUM(d.total_pax) OVER () AS ReportTotalPax
    FROM tmp_guest_details d
    ORDER BY
        d.report_date ASC,
        d.room_type_name ASC,
        d.detail_room ASC,
        d.sort_order_in_room ASC,
        d.guest_name ASC;
SQL;
    }
};
