<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_CODE = 'CHECKED_IN_GUESTS';
    private const REPORT_CODE = 'CHECKED_IN_GUESTS';
    private const TEMPLATE_CODE = 'CHECKED_IN_GUESTS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_checked_in_guests');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_checked_in_guests(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_order_by VARCHAR(20),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_note TINYINT
)
READS SQL DATA
BEGIN
    /* MySQL mapping of ProVista sp_144:
       vw_035 = booking_rooms + booking_room_guests + guests;
       SP2200 = booking_room_guests; SP2100 = booking_rooms. */
    SELECT
        'Y' AS UsedTo,
        CASE
            WHEN Title = 'Mr.' THEN 'M'
            WHEN Title IN ('Ms.', 'Mrs.') THEN 'F'
            WHEN Title = 'Dr.' THEN 'M'
            ELSE 'F'
        END AS Gender,
        temp.*
    FROM (
        SELECT ranked.*
        FROM (
            SELECT
                ROW_NUMBER() OVER (PARTITION BY src.BookingId, src.CustomerId ORDER BY src.ArrivalDateRaw) AS row_num,
                src.*
            FROM (
                SELECT
                    b.id AS BookingId,
                    br.id AS RentalRoomId,
                    brg.guest_id AS CustomerId,
                    b.booking_name AS BookingName,
                    COALESCE(br.room_number, '') AS Room,
                    rc.code AS RoomTypeCode,
                    TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
                    g.title AS Title,
                    COALESCE(brg.actual_arrival_date, br.actual_arrival_date, br.arrival_date) AS ArrivalDateRaw,
                    DATE_FORMAT(COALESCE(brg.actual_arrival_date, br.actual_arrival_date, br.arrival_date), '%d/%m/%Y') AS ActualArrivalDate,
                    DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDate,
                    br.ActutalNumOfDays AS RoomNight,
                    COALESCE(g.nationality_code, '') AS Nationality,
                    CASE WHEN COALESCE(p_show_note, 1) = 1 THEN COALESCE(br.note, b.note, '') ELSE '' END AS Note,
                    br.status AS Status,
                    br.CheckoutDate,
                    brg.is_primary AS IsMainGuest,
                    r.orders AS RoomOrder
                FROM booking_rooms br
                INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
                INNER JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.status IN (0, 1, 2, 5, 100, 101, 102)
                INNER JOIN guests g ON g.id = brg.guest_id
                INNER JOIN room_classes rc ON rc.id = br.room_class_id
                LEFT JOIN rooms r ON r.room_number = br.room_number
                WHERE br.deleted_at IS NULL
                  AND ((br.status = 100 AND br.ActutalNumOfDays <> 0) OR br.status <> 100)
                  AND br.id IN (
                      SELECT DISTINCT eligible_room.id
                      FROM booking_rooms eligible_room
                      INNER JOIN booking_room_guests eligible_guest ON eligible_guest.booking_room_id = eligible_room.id
                      WHERE eligible_room.deleted_at IS NULL
                        AND eligible_guest.guest_id IN (
                            SELECT qualifying_guest.guest_id
                            FROM booking_rooms qualifying_room
                            INNER JOIN bookings qualifying_booking ON qualifying_booking.id = qualifying_room.booking_id AND qualifying_booking.deleted_at IS NULL
                            INNER JOIN booking_room_guests qualifying_guest ON qualifying_guest.booking_room_id = qualifying_room.id
                            WHERE qualifying_room.deleted_at IS NULL
                              AND qualifying_room.status IN (1, 2, 5, 100, 101, 102)
                              AND COALESCE(qualifying_guest.actual_arrival_date, qualifying_room.actual_arrival_date, qualifying_room.arrival_date) BETWEEN p_from_date AND p_to_date
                              AND (p_company_id IS NULL OR p_company_id = 0 OR qualifying_booking.company_id = p_company_id)
                              AND (p_booking_id IS NULL OR p_booking_id = 0 OR qualifying_booking.id = p_booking_id)
                        )
                  )
            ) src
        ) ranked
        WHERE ranked.row_num = 1
          AND ranked.ArrivalDateRaw BETWEEN p_from_date AND p_to_date
          AND NOT EXISTS (
              SELECT 1 FROM booking_rooms moved_from
              WHERE moved_from.move_room = ranked.RentalRoomId
                AND moved_from.status = 100
                AND moved_from.CheckoutDate BETWEEN p_from_date AND p_to_date
                AND moved_from.ActutalNumOfDays <> 0
                AND moved_from.deleted_at IS NULL
          )
    ) temp
    ORDER BY
        CASE WHEN p_order_by = 'ArrivalDate' THEN temp.ArrivalDateRaw END DESC,
        CASE WHEN p_order_by = 'Room' THEN CAST(temp.Room AS UNSIGNED) END ASC,
        CASE WHEN p_order_by = 'DepartureDate' THEN temp.DepartureDate END DESC,
        CASE WHEN p_order_by = 'OpenTimeDi' THEN temp.CheckoutDate END ASC,
        temp.RoomOrder, temp.RentalRoomId, temp.IsMainGuest DESC, temp.GuestName;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            $this->parameter('p_from_date', 'date', 'date', 1), $this->parameter('p_to_date', 'date', 'date', 2),
            $this->parameter('p_order_by', 'varchar', 'varchar(20)', 3), $this->parameter('p_company_id', 'bigint', 'bigint', 4),
            $this->parameter('p_booking_id', 'bigint', 'bigint', 5), $this->parameter('p_show_note', 'tinyint', 'tinyint', 6),
        ];
        $fields = ['UsedTo','Gender','BookingId','RentalRoomId','CustomerId','BookingName','Room','RoomTypeCode','GuestName','Title','ActualArrivalDate','DepartureDate','RoomNight','Nationality','Note','Status','CheckoutDate','IsMainGuest','RoomOrder'];
        $fieldSchema = array_map(fn ($name) => ['name' => $name, 'type' => in_array($name, ['BookingId','RoomNight','IsMainGuest','RoomOrder'], true) ? 'integer' : 'string', 'nullable' => false], $fields);

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE_CODE], [
            'name' => 'Dữ liệu khách đã nhận phòng', 'description' => 'Khách thuộc các phòng đang checked-in, lọc theo ngày nhận phòng thực tế.',
            'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_checked_in_guests',
            'parameter_schema' => json_encode($parameters), 'field_schema' => json_encode($fieldSchema),
            'sample_parameters' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_order_by' => 'ArrivalDate', 'p_company_id' => null, 'p_booking_id' => null, 'p_show_note' => 1]),
            'max_rows' => 2000, 'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->value('id');
        $template = require database_path('report_templates/checked_in_guests_reference.php');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE_CODE], [
            'group' => 'Báo cáo phòng', 'name' => 'Danh sách khách đã nhận phòng - Mẫu chuẩn', 'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_order_by' => 'ArrivalDate', 'p_company_id' => null, 'p_booking_id' => null, 'p_show_note' => 1]),
            'page_size' => 'A4', 'page_orientation' => 'portrait', 'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
            'content_json' => json_encode([]), 'content_html' => '', 'css' => '', 'is_default' => false, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $template->apply();
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT_CODE], [
            'name' => 'Báo cáo danh sách khách đã nhận phòng', 'group' => 'Báo cáo phòng',
            'description' => 'Danh sách khách tại các phòng đã nhận phòng, theo ngày nhận phòng thực tế.', 'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode([
                ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
                ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
                ['name' => 'p_order_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'ArrivalDate', 'required' => true, 'options' => [['value' => 'ArrivalDate', 'label' => 'Ngày đến'], ['value' => 'Room', 'label' => 'Phòng'], ['value' => 'DepartureDate', 'label' => 'Ngày đi'], ['value' => 'OpenTimeDi', 'label' => 'Giờ trả phòng']]],
                ['name' => 'p_company_id', 'label' => 'Lọc theo công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'companies'],
                ['name' => 'p_booking_id', 'label' => 'Lọc theo đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'bookings'],
                ['name' => 'p_show_note', 'label' => 'Hiển thị ghi chú', 'control' => 'checkbox', 'default' => true, 'required' => false, 'options' => []],
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 12, 'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation','frontdesk']),
            'menu_top_order' => 20, 'menu_group_order' => 10, 'menu_item_order' => 12, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');
        if ($reportId) { DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete(); DB::table('report_definitions')->where('id', $reportId)->delete(); }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_checked_in_guests');
    }

    private function parameter(string $name, string $dataType, string $databaseType, int $position): array
    {
        return ['name' => $name, 'mode' => 'IN', 'data_type' => $dataType, 'database_type' => $databaseType, 'max_length' => null, 'numeric_precision' => null, 'numeric_scale' => null, 'position' => $position, 'required' => true];
    }
};
