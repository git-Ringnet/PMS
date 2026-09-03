<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_cancelled_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_cancelled_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_view_type VARCHAR(20), IN p_booking_id BIGINT,
    IN p_division VARCHAR(20), IN p_group_by_reason TINYINT
)
READS SQL DATA
BEGIN
    SELECT ROW_NUMBER() OVER (ORDER BY l.cancelled_at, b.id, br.room_number, br.id) AS STT,
        br.id AS RoomId, CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode, b.id AS BookingId,
        b.booking_name AS BookingName, c.name AS Company, DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS DateDangKy,
        rs.name AS BookingStatus, br.room_number AS Room, DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDate, DATE_FORMAT(l.cancelled_at, '%d/%m/%Y') AS CancelDate,
        TIME_FORMAT(l.cancelled_at, '%H:%i') AS CancelTime, COALESCE(l.cancelled_by_username, '') AS UserName,
        COALESCE(NULLIF(l.note, ''), cr.name, br.reason, '') AS CancelReason,
        COALESCE(NULLIF(l.note, ''), cr.name, br.reason, 'Không có lý do') AS CancelReasonGroup,
        DATEDIFF(br.arrival_date, DATE(l.cancelled_at)) AS SoCancelDate, br.rate AS Rate, rc.name AS RoomType, hs.division AS Division
    FROM booking_cancel_logs l
    INNER JOIN booking_rooms br ON br.id = l.booking_room_id
    INNER JOIN bookings b ON b.id = COALESCE(l.booking_id, br.booking_id) AND b.deleted_at IS NULL
    LEFT JOIN room_classes rc ON rc.id = br.room_class_id
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
    LEFT JOIN cancel_reasons cr ON cr.id = l.cancel_reason_id
    LEFT JOIN hotel_settings hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE l.cancel_type = 'room' AND br.deleted_at IS NULL
      AND (p_division IS NULL OR p_division = '' OR p_division = '__current__' OR hs.division = p_division)
      AND (p_booking_id IS NULL OR b.id = p_booking_id)
      AND ((COALESCE(p_view_type, 'CancelDate') = 'ArrivalDate' AND br.arrival_date >= p_from_date AND br.arrival_date < DATE_ADD(p_to_date, INTERVAL 1 DAY) AND br.status = 3)
        OR (COALESCE(p_view_type, 'CancelDate') <> 'ArrivalDate' AND l.cancelled_at >= p_from_date AND l.cancelled_at < DATE_ADD(p_to_date, INTERVAL 1 DAY)))
    ORDER BY CASE WHEN COALESCE(p_group_by_reason, 0) = 1 THEN COALESCE(NULLIF(l.note, ''), cr.name, br.reason, 'Không có lý do') END,
        l.cancelled_at, b.id, br.room_number, br.id;
END
SQL);

        $sourceId = DB::table('report_data_sources')->where('code', 'CANCELLED_ROOMS')->value('id');
        $source = DB::table('report_data_sources')->where('id', $sourceId)->first();
        $schema = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_view_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => true],
            ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 4, 'required' => false],
            ['name' => 'p_division', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
            ['name' => 'p_group_by_reason', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
        ];
        $fields = json_decode($source->field_schema ?? '[]', true) ?: [];
        $fields[] = ['name' => 'CancelReasonGroup', 'type' => 'string', 'nullable' => true];
        DB::table('report_data_sources')->where('id', $sourceId)->update([
            'parameter_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE), 'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE), 'sample_parameters' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_view_type' => 'CancelDate', 'p_booking_id' => null, 'p_division' => '', 'p_group_by_reason' => 0]), 'updated_at' => now(),
        ]);
        DB::table('report_definitions')->where('code', 'CANCELLED_ROOMS')->update([
            'parameter_ui_schema' => json_encode([
                ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
                ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
                ['name' => 'p_view_type', 'label' => 'Loại ngày', 'control' => 'radio', 'default' => 'CancelDate', 'required' => true, 'options' => [['value' => 'CancelDate', 'label' => 'Xem theo ngày hủy phòng'], ['value' => 'ArrivalDate', 'label' => 'Xem theo ngày đến']]],
                ['name' => 'p_booking_id', 'label' => 'Booking', 'control' => 'text', 'default' => '', 'required' => false, 'options' => []],
                ['name' => 'p_division', 'label' => 'Chi nhánh', 'control' => 'select', 'default' => '__current__', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả chi nhánh'], ['value' => '__current__', 'label' => 'Chi nhánh hiện tại']]],
                ['name' => 'p_group_by_reason', 'label' => 'Nhóm theo lý do hủy phòng', 'control' => 'checkbox', 'default' => false, 'required' => false, 'options' => []],
            ], JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sidebar-only correction; previous procedure signature is not reconstructed.
    }
};
