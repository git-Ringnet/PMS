<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_CODE = 'ARRIVING_ROOMS';

    private const REPORT_CODE = 'ARRIVING_ROOMS';

    private const TEMPLATE_CODE = 'ARRIVING_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->createProcedure();
        $this->seedReportConfiguration();

        $referenceTemplate = require database_path('report_templates/arriving_rooms_reference.php');
        $referenceTemplate->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }
        DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_arriving_rooms');
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_arriving_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_arriving_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT,
    IN p_show_room_rate TINYINT
)
READS SQL DATA
BEGIN
    SELECT result.*
    FROM (
        SELECT
            DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
            CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomTypeCode,
            CASE WHEN brg.is_primary = 1 THEN rc.name ELSE NULL END AS RoomType,
            CASE WHEN brg.is_primary = 1 THEN orc.name ELSE NULL END AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
            CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
            CASE WHEN brg.is_primary = 1 THEN br.adults ELSE NULL END AS Adult,
            CASE WHEN brg.is_primary = 1 THEN br.children_qty ELSE NULL END AS Child,
            CASE WHEN brg.is_primary = 1 THEN CONCAT(br.adults, ' / ', br.children_qty) ELSE NULL END AS AdultChild,
            CASE WHEN brg.is_primary = 1 AND COALESCE(p_show_room_rate, 1) = 1 THEN br.rate ELSE NULL END AS Rate,
            CASE WHEN brg.is_primary = 1 THEN (
                SELECT GROUP_CONCAT(sr.code ORDER BY sr.sort_order, sr.code SEPARATOR ', ')
                FROM booking_room_special_requests AS brsr
                INNER JOIN special_requests AS sr ON sr.id = brsr.special_request_id
                WHERE brsr.booking_room_id = br.id
            ) ELSE NULL END AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            brg.is_primary AS IsMainGuest,
            g.guest_type AS GuestType,
            b.booking_name AS BookingName,
            COALESCE(brg.breakfast, br.breakfast) AS Breakfast,
            g.phone AS Phone,
            CASE g.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            COALESCE(rs.vietnamese, rs.name) AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_guests AS brg ON brg.booking_room_id = br.id AND brg.status IN (0, 1, 2)
        INNER JOIN guests AS g ON g.id = brg.guest_id
        INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN room_classes AS orc
            ON orc.id = CAST(SUBSTRING_INDEX(br.original_room_class_id, '-', 1) AS UNSIGNED)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND br.arrival_date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_show_main_guest, 1) = 0 OR brg.is_primary = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND NOT EXISTS (
              SELECT 1
              FROM booking_rooms AS moved_from
              WHERE moved_from.move_room = br.id AND moved_from.deleted_at IS NULL
          )

        UNION ALL

        SELECT
            DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            NULL AS Room,
            NULL AS RoomTypeCode,
            NULL AS RoomType,
            NULL AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(bc.title, ''), bc.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            NULL AS ExtraBed,
            NULL AS RoomNight,
            NULL AS Adult,
            NULL AS Child,
            NULL AS AdultChild,
            NULL AS Rate,
            NULL AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            -1 AS IsMainGuest,
            bc.age_group AS GuestType,
            b.booking_name AS BookingName,
            br.breakfast AS Breakfast,
            bc.phone AS Phone,
            CASE bc.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            NULL AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_children AS bc ON bc.booking_room_id = br.id AND bc.child_status IN (0, 1, 2)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
          AND br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND br.arrival_date BETWEEN p_from_date AND p_to_date
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND NOT EXISTS (
              SELECT 1
              FROM booking_rooms AS moved_from
              WHERE moved_from.move_room = br.id AND moved_from.deleted_at IS NULL
          )
    ) AS result
    ORDER BY result.RoomOrder, result.RentalRoomId, result.IsMainGuest DESC, result.GuestName;
END
SQL);
    }

    private function seedReportConfiguration(): void
    {
        $database = DB::connection()->getDatabaseName();
        $now = now();
        $parameterSchema = [
            $this->parameter('p_from_date', 'date', 'date', 1),
            $this->parameter('p_to_date', 'date', 'date', 2),
            $this->parameter('p_room_class_id', 'bigint', 'bigint', 3),
            $this->parameter('p_registration_status_id', 'bigint', 'bigint', 4),
            $this->parameter('p_area', 'varchar', 'varchar(100)', 5),
            $this->parameter('p_company_id', 'bigint', 'bigint', 6),
            $this->parameter('p_booking_id', 'bigint', 'bigint', 7),
            $this->parameter('p_show_main_guest', 'tinyint', 'tinyint', 8),
            $this->parameter('p_show_room_rate', 'tinyint', 'tinyint', 9),
        ];
        $fieldSchema = collect([
            'ArrivalDateGroup', 'Booking', 'BookingId', 'RegisterId', 'RentalRoomId', 'Room', 'RoomTypeCode', 'RoomType',
            'RoomTypeOriginal', 'GuestName', 'ArrivalDate', 'DepartureDate', 'ExtraBed', 'RoomNight',
            'Adult', 'Child', 'AdultChild', 'Rate', 'Special', 'CompanyId', 'Company', 'Note',
            'IsMainGuest', 'GuestType', 'BookingName', 'Breakfast', 'Phone', 'Gender', 'RoomOrder',
            'BookingStatusName', 'SpecialDangKy',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, ['BookingId', 'RegisterId', 'ExtraBed', 'RoomNight', 'Adult', 'Child', 'CompanyId', 'IsMainGuest', 'RoomOrder'], true)
                ? 'integer'
                : ($name === 'Rate' ? 'number' : 'string'),
            'nullable' => ! in_array($name, ['Booking', 'BookingId', 'RegisterId', 'RentalRoomId', 'GuestName', 'ArrivalDate', 'DepartureDate'], true),
        ])->all();

        DB::table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE_CODE],
            [
                'name' => 'Dữ liệu báo cáo phòng đến',
                'description' => 'MySQL chuyển đổi từ ProVista sp_006, dùng các bảng booking của PMS.',
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => 'rpt_arriving_rooms',
                'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode([
                    'p_from_date' => now()->toDateString(),
                    'p_to_date' => now()->toDateString(),
                    'p_room_class_id' => null,
                    'p_registration_status_id' => null,
                    'p_area' => null,
                    'p_company_id' => null,
                    'p_booking_id' => null,
                    'p_show_main_guest' => 1,
                    'p_show_room_rate' => 1,
                ]),
                'max_rows' => 2000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->value('id');

        $blocks = $this->templateBlocks();
        DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE_CODE],
            [
                'group' => 'Báo cáo phòng',
                'name' => 'Báo cáo phòng đến - Mẫu chuẩn',
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode([
                    'p_from_date' => now()->toDateString(),
                    'p_to_date' => now()->toDateString(),
                    'p_room_class_id' => null,
                    'p_registration_status_id' => null,
                    'p_area' => null,
                    'p_company_id' => null,
                    'p_booking_id' => null,
                    'p_show_main_guest' => 1,
                    'p_show_room_rate' => 1,
                ]),
                'page_size' => 'A4',
                'page_orientation' => 'landscape',
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 8,
                'margin_right' => 8,
                'content_json' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                'content_html' => $this->templateHtml(),
                'css' => $this->templateCss(),
                'is_default' => false,
                'version' => '1.0',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        DB::table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT_CODE],
            [
                'name' => 'Báo cáo phòng đến',
                'group' => 'Báo cáo phòng',
                'description' => 'Danh sách khách/phòng có ngày đến trong khoảng được chọn.',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($this->parameterUiSchema(), JSON_UNESCAPED_UNICODE),
                'sort_order' => 10,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 10,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    private function parameter(string $name, string $dataType, string $databaseType, int $position): array
    {
        return [
            'name' => $name,
            'mode' => 'IN',
            'data_type' => $dataType,
            'database_type' => $databaseType,
            'max_length' => $dataType === 'varchar' ? 100 : null,
            'numeric_precision' => null,
            'numeric_scale' => null,
            'position' => $position,
            'required' => true,
        ];
    }

    private function parameterUiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_room_class_id', 'label' => 'Chọn loại phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'room-classes'],
            ['name' => 'p_registration_status_id', 'label' => 'Chọn tình trạng đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'registration-statuses'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'areas'],
            ['name' => 'p_company_id', 'label' => 'Công ty / lữ hành', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'companies'],
            ['name' => 'p_booking_id', 'label' => 'Đăng ký phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'bookings'],
            ['name' => 'p_show_main_guest', 'label' => 'Chỉ hiển thị khách chính', 'control' => 'checkbox', 'default' => true, 'required' => false, 'options' => []],
            ['name' => 'p_show_room_rate', 'label' => 'Hiển thị giá phòng', 'control' => 'checkbox', 'default' => true, 'required' => false, 'options' => []],
        ];
    }

    private function templateBlocks(): array
    {
        return [
            'header' => [
                ['id' => 'arriving_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG ĐẾN</h1>', 'style' => ['textAlign' => 'center', 'fontSize' => '24px', 'fontWeight' => 'bold', 'marginBottom' => '4px']],
                ['id' => 'arriving_period', 'type' => 'text', 'content' => '<p class="report-period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'fontSize' => '12px', 'marginBottom' => '12px']],
                ['id' => 'arriving_meta', 'type' => 'text', 'content' => '<p class="report-meta">Người lập: {{report.generated_by}} &nbsp; | &nbsp; Thời điểm: {{report.generated_at}}</p>', 'style' => ['textAlign' => 'right', 'fontSize' => '10px', 'marginBottom' => '6px']],
            ],
            'detail' => [[
                'id' => 'arriving_rows',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'isNew' => false,
                'columns' => [
                    ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'center'],
                    ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
                    ['header' => 'Loại phòng', 'value' => 'row.RoomTypeCode', 'width' => '8%', 'align' => 'center'],
                    ['header' => 'Tên khách', 'value' => 'row.GuestName', 'width' => '15%', 'align' => 'left'],
                    ['header' => 'Giới tính', 'value' => 'row.Gender', 'width' => '6%', 'align' => 'center'],
                    ['header' => 'Ngày đến', 'value' => 'row.ArrivalDate', 'width' => '11%', 'align' => 'center'],
                    ['header' => 'Ngày đi', 'value' => 'row.DepartureDate', 'width' => '11%', 'align' => 'center'],
                    ['header' => 'Đêm', 'value' => 'row.RoomNight', 'width' => '5%', 'align' => 'center'],
                    ['header' => 'NL / TE', 'value' => 'row.AdultChild', 'width' => '7%', 'align' => 'center'],
                    ['header' => 'Giá phòng', 'value' => 'row.Rate', 'width' => '9%', 'align' => 'right'],
                    ['header' => 'Công ty', 'value' => 'row.Company', 'width' => '9%', 'align' => 'left'],
                    ['header' => 'Yêu cầu đặc biệt', 'value' => 'row.Special', 'width' => '12%', 'align' => 'left'],
                ],
                'style' => ['marginTop' => '8px', 'marginBottom' => '8px'],
            ]],
            'footer' => [
                ['id' => 'arriving_total', 'type' => 'text', 'content' => '<p class="report-total">Tổng số dòng: {{summary.row_count}}</p>', 'style' => ['textAlign' => 'right', 'fontSize' => '11px', 'fontWeight' => 'bold', 'marginTop' => '6px']],
            ],
        ];
    }

    private function templateHtml(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div id="arriving_title" style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:4px"><h1>BÁO CÁO PHÒNG ĐẾN</h1></div>
  <div id="arriving_period" style="text-align:center;font-size:12px;margin-bottom:12px"><p class="report-period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
  <div id="arriving_meta" style="text-align:right;font-size:10px;margin-bottom:6px"><p class="report-meta">Người lập: {{report.generated_by}} &nbsp; | &nbsp; Thời điểm: {{report.generated_at}}</p></div>
</div>
<div class="report-detail-band">
  <table class="arriving-table">
    <thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Loại phòng</th><th>Tên khách</th><th>Giới tính</th><th>Ngày đến</th><th>Ngày đi</th><th>Đêm</th><th>NL / TE</th><th>Giá phòng</th><th>Công ty</th><th>Yêu cầu đặc biệt</th></tr></thead>
    <tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomTypeCode}}</td><td>{{row.GuestName}}</td><td>{{row.Gender}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.AdultChild}}</td><td>{{row.Rate}}</td><td>{{row.Company}}</td><td>{{row.Special}}</td></tr></tbody>
  </table>
</div>
<div class="report-footer-band"><p class="report-total">Tổng số dòng: {{summary.row_count}}</p></div>
HTML;
    }

    private function templateCss(): string
    {
        return <<<'CSS'
body { color: #111827; font-family: Arial, sans-serif; font-size: 10px; }
h1 { margin: 0; font-size: 24px; }
.report-period { margin: 0; font-weight: 700; }
.report-meta { margin: 0; color: #475569; }
.arriving-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.arriving-table th, .arriving-table td { border: 1px solid #94a3b8; padding: 5px 4px; overflow-wrap: anywhere; vertical-align: middle; }
.arriving-table th { background: #e2e8f0; text-align: center; font-weight: 700; }
.arriving-table th:nth-child(1) { width: 6%; }
.arriving-table th:nth-child(2) { width: 5%; }
.arriving-table th:nth-child(3) { width: 7%; }
.arriving-table th:nth-child(4) { width: 14%; }
.arriving-table th:nth-child(5) { width: 6%; }
.arriving-table th:nth-child(6), .arriving-table th:nth-child(7) { width: 11%; }
.arriving-table th:nth-child(8) { width: 4%; }
.arriving-table th:nth-child(9) { width: 6%; }
.arriving-table th:nth-child(10) { width: 8%; }
.arriving-table th:nth-child(11) { width: 10%; }
.arriving-table th:nth-child(12) { width: 12%; }
.arriving-table td:nth-child(1), .arriving-table td:nth-child(2), .arriving-table td:nth-child(3), .arriving-table td:nth-child(5), .arriving-table td:nth-child(6), .arriving-table td:nth-child(7), .arriving-table td:nth-child(8), .arriving-table td:nth-child(9) { text-align: center; }
.arriving-table td:nth-child(10) { text-align: right; }
.report-total { margin: 8px 0 0; text-align: right; font-weight: 700; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
