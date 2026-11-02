<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connections = array_unique(array_filter([
            DB::getDefaultConnection(),
            'mysql',
            'mysql_hkt1',
            'mysql_hkt2',
            'mysql_hkt3',
            'mysql_hkt4',
        ]));

        foreach ($connections as $connection) {
            try {
                $db = DB::connection($connection);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                $schema = Schema::connection($connection);

                // 1. Cập nhật Stored Procedure rpt_company_occupancy_detail
                $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy_detail`');
                $db->unprepared($this->companyOccupancyDetailProcedure());

                // 2. Cập nhật metadata report_data_sources
                if ($schema->hasTable('report_data_sources')) {
                    $db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY_DETAIL')->update([
                        'parameter_schema' => json_encode($this->parameterSchema(), JSON_UNESCAPED_UNICODE),
                        'field_schema' => json_encode($this->fieldsSchema(), JSON_UNESCAPED_UNICODE),
                        'sample_parameters' => json_encode($this->sampleParameters(), JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }

                // 3. Cập nhật metadata report_definitions
                if ($schema->hasTable('report_definitions')) {
                    $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY_DETAIL')->update([
                        'parameter_ui_schema' => json_encode($this->uiSchema(), JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Tiếp tục với các kết nối còn lại nếu có kết nối offline/chưa khởi tạo
            }
        }
    }

    public function down(): void
    {
        // Giữ procedure phiên bản ổn định, không rollback gây mất báo cáo
    }

    private function companyOccupancyDetailProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy_detail(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, CURRENT_DATE());
    DECLARE v_system_date DATE DEFAULT CURRENT_DATE();
    DECLARE v_operation_date DATE;
    DECLARE v_from_past DATE;
    DECLARE v_to_past DATE;
    DECLARE v_from_future DATE;
    DECLARE v_to_future DATE;
    DECLARE v_prefix VARCHAR(50) DEFAULT '';

    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;
    IF v_prefix IS NULL THEN SET v_prefix = ''; END IF;

    -- Kiểm tra ngày vận hành
    SELECT CAST(value AS DATE) INTO v_operation_date 
      FROM hotel_configs 
     WHERE LOWER(name) = 'operationdate' AND value IS NOT NULL AND value <> '' 
     LIMIT 1;
    IF v_operation_date IS NOT NULL AND v_from < v_operation_date THEN
        SET v_from = v_operation_date;
    END IF;

    -- Xác định ngày hệ thống
    SELECT CAST(value AS DATE) INTO v_system_date
      FROM hotel_configs
     WHERE LOWER(name) = 'systemdate' AND value IS NOT NULL AND value <> ''
     LIMIT 1;
    IF v_system_date IS NULL THEN
        SELECT DATE(system_date) INTO v_system_date
          FROM system_date_rolls
         ORDER BY id DESC
         LIMIT 1;
    END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;

    -- Phân tách mốc quá khứ và tương lai
    SET v_from_past = v_from;
    IF v_to < v_system_date THEN
        SET v_to_past = v_to;
    ELSE
        SET v_to_past = DATE_SUB(v_system_date, INTERVAL 1 DAY);
    END IF;

    IF v_system_date <= v_from THEN
        SET v_from_future = v_from;
    ELSE
        SET v_from_future = v_system_date;
    END IF;
    SET v_to_future = v_to;

    DROP TEMPORARY TABLE IF EXISTS tmp_detail_items;
    CREATE TEMPORARY TABLE tmp_detail_items (
        BookingId BIGINT,
        BookingCode VARCHAR(100),
        ReferenceCode VARCHAR(100),
        CompanyId BIGINT,
        CompanyCode VARCHAR(50),
        CompanyName VARCHAR(255),
        GuestName VARCHAR(255),
        MarketSegment VARCHAR(50),
        SourceCode VARCHAR(50),
        BookingDate DATE,
        ArrivalDate DATE,
        DepartureDate DATE,
        NoOfNight INT DEFAULT 0,
        NoOfRoom INT DEFAULT 0,
        RoomNight INT DEFAULT 0,
        GuestNight INT DEFAULT 0,
        Foc INT DEFAULT 0,
        Hu INT DEFAULT 0,
        RoomRevenue DECIMAL(15,2) DEFAULT 0,
        FbRevenue DECIMAL(15,2) DEFAULT 0,
        OtherRevenue DECIMAL(15,2) DEFAULT 0,
        OriginalAmount DECIMAL(15,2) DEFAULT 0,
        RoomType VARCHAR(50),
        Nationality VARCHAR(50)
    );

    -- 1. Nguồn quá khứ: service_bills + room_night_bills
    IF v_from_past <= v_to_past THEN
        INSERT INTO tmp_detail_items
        SELECT
            b.id AS BookingId,
            CONCAT(v_prefix, b.id) AS BookingCode,
            COALESCE(NULLIF(b.external_booking_code, ''), '') AS ReferenceCode,
            c.id AS CompanyId,
            COALESCE(c.code, 'CTY0001') AS CompanyCode,
            COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
            COALESCE(NULLIF(b.booking_name, ''), 'Khách lẻ') AS GuestName,
            COALESCE(NULLIF(m.code, ''), '') AS MarketSegment,
            COALESCE(NULLIF(cs.code, ''), '') AS SourceCode,
            DATE(b.booking_date) AS BookingDate,
            DATE(b.arrival_date) AS ArrivalDate,
            DATE(b.departure_date) AS DepartureDate,
            GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0) AS NoOfNight,
            COALESCE((SELECT COUNT(*) FROM booking_rooms br0 WHERE br0.booking_id = b.id AND br0.status NOT IN (3,100)), 1) AS NoOfRoom,
            CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN 1 ELSE 0 END AS RoomNight,
            CASE 
                WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 
                THEN COALESCE(NULLIF(COALESCE(rnb.adult, 0) + COALESCE(rnb.child, 0), 0), br.adults + br.children_qty, 1) 
                ELSE 0 
            END AS GuestNight,
            CASE WHEN sb.ServiceId = 'RM' AND (rnb.rate_code = 'FOC' OR COALESCE(rnb.rate, 0) = 0) THEN 1 ELSE 0 END AS Foc,
            CASE WHEN sb.ServiceId = 'RM' AND rnb.rate_code = 'HU' THEN 1 ELSE 0 END AS Hu,
            CASE 
                WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') 
                THEN COALESCE(sb.Amount, 0)
                ELSE 0 
            END AS RoomRevenue,
            CASE 
                WHEN COALESCE(sb.DepartmentId, '') = 'FB' OR sb.ServiceId IN ('FB','OT','RB','RF') 
                THEN COALESCE(sb.Amount, 0)
                ELSE 0 
            END AS FbRevenue,
            CASE 
                WHEN sb.ServiceId NOT IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','FB','OT','RB','RF')
                     AND COALESCE(sb.DepartmentId, '') <> 'FB'
                THEN COALESCE(sb.Amount, 0)
                ELSE 0 
            END AS OtherRevenue,
            CASE WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN COALESCE(NULLIF(rnb.rate, 0), sb.Amount, 0) ELSE 0 END AS OriginalAmount,
            COALESCE((SELECT rc.code FROM booking_rooms br2 LEFT JOIN room_classes rc ON rc.id = br2.room_class_id WHERE br2.booking_id = b.id AND br2.status NOT IN (3,100) ORDER BY br2.id LIMIT 1), 'STD') AS RoomType,
            COALESCE((SELECT COALESCE(g.nationality_code, 'VNM') FROM booking_room_guests brg INNER JOIN guests g ON g.id = brg.guest_id INNER JOIN booking_rooms br3 ON br3.id = brg.booking_room_id WHERE br3.booking_id = b.id ORDER BY brg.is_primary DESC, brg.id LIMIT 1), 'VNM') AS Nationality
        FROM service_bills sb
        LEFT JOIN booking_rooms br ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
        LEFT JOIN bookings b ON b.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))
        LEFT JOIN companies c ON c.id = COALESCE(b.company_id, sb.CompanyId2)
        LEFT JOIN markets m ON m.id = b.market_id
        LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
        LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
        WHERE COALESCE(sb.Edit, 0) = 0 AND COALESCE(sb.Status, 1) <> 3
          AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN v_from_past AND v_to_past
          AND b.deleted_at IS NULL AND b.status NOT IN (3, 4)
          AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
          AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
          AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
          AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
          AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number WHERE bra.booking_id = b.id AND rra.area = p_area));
    END IF;

    -- 2. Nguồn tương lai: booking_rooms + bookings
    IF v_from_future <= v_to_future THEN
        INSERT INTO tmp_detail_items
        SELECT
            b.id AS BookingId,
            CONCAT(v_prefix, b.id) AS BookingCode,
            COALESCE(NULLIF(b.external_booking_code, ''), '') AS ReferenceCode,
            c.id AS CompanyId,
            COALESCE(c.code, 'CTY0001') AS CompanyCode,
            COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
            COALESCE(NULLIF(b.booking_name, ''), 'Khách lẻ') AS GuestName,
            COALESCE(NULLIF(m.code, ''), '') AS MarketSegment,
            COALESCE(NULLIF(cs.code, ''), '') AS SourceCode,
            DATE(b.booking_date) AS BookingDate,
            DATE(b.arrival_date) AS ArrivalDate,
            DATE(b.departure_date) AS DepartureDate,
            GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 0) AS NoOfNight,
            COALESCE((SELECT COUNT(*) FROM booking_rooms br0 WHERE br0.booking_id = b.id AND br0.status NOT IN (3,100)), 1) AS NoOfRoom,
            1 AS RoomNight,
            GREATEST(COALESCE(br.adults + br.children_qty, 1), 1) AS GuestNight,
            CASE WHEN COALESCE(br.rate_code, '') = 'FOC' OR COALESCE(br.rate, 0) = 0 THEN 1 ELSE 0 END AS Foc,
            CASE WHEN COALESCE(br.rate_code, '') = 'HU' THEN 1 ELSE 0 END AS Hu,
            COALESCE(br.rate, 0) AS RoomRevenue,
            0 AS FbRevenue,
            0 AS OtherRevenue,
            COALESCE(br.rate, 0) AS OriginalAmount,
            COALESCE((SELECT rc.code FROM room_classes rc WHERE rc.id = br.room_class_id LIMIT 1), 'STD') AS RoomType,
            COALESCE((SELECT COALESCE(g.nationality_code, 'VNM') FROM booking_room_guests brg INNER JOIN guests g ON g.id = brg.guest_id WHERE brg.booking_room_id = br.id ORDER BY brg.is_primary DESC, brg.id LIMIT 1), 'VNM') AS Nationality
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        LEFT JOIN companies c ON c.id = b.company_id
        LEFT JOIN markets m ON m.id = b.market_id
        LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
        WHERE b.deleted_at IS NULL 
          AND b.status NOT IN (3, 4)
          AND br.status NOT IN (3, 100)
          AND (br.arrival_date <= v_to_future AND br.departure_date > v_from_future)
          AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
          AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
          AND (COALESCE(p_source_code, '') = '' OR CAST(b.customer_source_id AS CHAR) = p_source_code OR cs.code = p_source_code)
          AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
          AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM rooms rra WHERE rra.room_number = br.room_number AND rra.area = p_area));
    END IF;

    -- 3. Query trả kết quả 21 field
    SELECT
        BookingCode,
        ReferenceCode,
        CompanyName,
        GuestName,
        MarketSegment,
        SourceCode,
        DATE_FORMAT(BookingDate, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(ArrivalDate, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(DepartureDate, '%d/%m/%Y') AS DepartureDate,
        NoOfNight,
        NoOfRoom,
        CAST(SUM(RoomNight) AS SIGNED) AS RoomNight,
        CAST(SUM(GuestNight) AS SIGNED) AS GuestNight,
        CASE WHEN SUM(RoomNight) > 0 THEN ROUND(SUM(RoomRevenue) / SUM(RoomNight), 0) ELSE 0 END AS AverageRate,
        CASE WHEN SUM(RoomNight - Foc - Hu) > 0 THEN ROUND(SUM(RoomRevenue) / SUM(RoomNight - Foc - Hu), 0) ELSE 0 END AS AverageRateOriginal,
        ROUND(SUM(RoomRevenue), 0) AS RoomRevenue,
        ROUND(SUM(FbRevenue), 0) AS FbRevenue,
        ROUND(SUM(OtherRevenue), 0) AS OtherRevenue,
        ROUND(SUM(RoomRevenue + FbRevenue + OtherRevenue), 0) AS TotalRevenue,
        RoomType,
        Nationality
    FROM tmp_detail_items
    GROUP BY
        BookingId,
        BookingCode,
        ReferenceCode,
        CompanyName,
        GuestName,
        MarketSegment,
        SourceCode,
        BookingDate,
        ArrivalDate,
        DepartureDate,
        NoOfNight,
        NoOfRoom,
        RoomType,
        Nationality
    HAVING (SUM(RoomNight) > 0 OR SUM(RoomRevenue + FbRevenue + OtherRevenue) <> 0)
    ORDER BY ArrivalDate, BookingCode;

    DROP TEMPORARY TABLE IF EXISTS tmp_detail_items;
END;
SQL;
    }

    private function parameterSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_area', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 3, 'required' => false],
            ['name' => 'p_company', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 4, 'required' => false],
            ['name' => 'p_segment', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 5, 'required' => false],
            ['name' => 'p_user_sale', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 6, 'required' => false],
            ['name' => 'p_source_code', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar', 'position' => 7, 'required' => false],
        ];
    }

    private function fieldsSchema(): array
    {
        return [
            ['name' => 'BookingCode', 'type' => 'string', 'nullable' => true],
            ['name' => 'ReferenceCode', 'type' => 'string', 'nullable' => true],
            ['name' => 'CompanyName', 'type' => 'string', 'nullable' => true],
            ['name' => 'GuestName', 'type' => 'string', 'nullable' => true],
            ['name' => 'MarketSegment', 'type' => 'string', 'nullable' => true],
            ['name' => 'SourceCode', 'type' => 'string', 'nullable' => true],
            ['name' => 'BookingDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'ArrivalDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'DepartureDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'NoOfNight', 'type' => 'integer', 'nullable' => true],
            ['name' => 'NoOfRoom', 'type' => 'integer', 'nullable' => true],
            ['name' => 'RoomNight', 'type' => 'integer', 'nullable' => true],
            ['name' => 'GuestNight', 'type' => 'integer', 'nullable' => true],
            ['name' => 'AverageRate', 'type' => 'number', 'nullable' => true],
            ['name' => 'AverageRateOriginal', 'type' => 'number', 'nullable' => true],
            ['name' => 'RoomRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'FbRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'OtherRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'TotalRevenue', 'type' => 'number', 'nullable' => true],
            ['name' => 'RoomType', 'type' => 'string', 'nullable' => true],
            ['name' => 'Nationality', 'type' => 'string', 'nullable' => true],
        ];
    }

    private function sampleParameters(): array
    {
        return [
            'p_from_date' => '2026-10-30',
            'p_to_date' => '2026-10-30',
            'p_area' => '',
            'p_company' => '',
            'p_segment' => '',
            'p_user_sale' => '',
            'p_source_code' => '',
        ];
    }

    private function uiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_source_code', 'label' => 'Nguồn khách', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Tất cả']]],
            ['name' => 'p_user_sale', 'label' => 'Người bán', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
        ];
    }
};

