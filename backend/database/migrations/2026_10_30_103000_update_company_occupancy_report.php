<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
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
        $templateRef = require database_path('report_templates/company_occupancy_reference.php');
        $def = $templateRef->definition();

        foreach ($connections as $connName) {
            try {
                $db = DB::connection($connName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                // 1. Cập nhật Stored Procedure
                $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy`');
                $db->unprepared($this->companyOccupancyProcedure());

                // 2. Cập nhật Template
                $db->table('templates')->where('report', 'COMPANY_OCCUPANCY_REFERENCE')->update([
                    'page_size' => $def['page_size'],
                    'page_orientation' => $def['page_orientation'],
                    'margin_top' => $def['margin_top'],
                    'margin_right' => $def['margin_right'],
                    'margin_bottom' => $def['margin_bottom'],
                    'margin_left' => $def['margin_left'],
                    'content_json' => json_encode($def['content_json'], JSON_UNESCAPED_UNICODE),
                    'content_html' => $def['content_html'],
                    'css' => $def['css'],
                    'updated_at' => now(),
                ]);

                // 3. Cập nhật parameter_ui_schema và parameter_schema
                $db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY')->update([
                    'parameter_ui_schema' => json_encode($this->uiSchema(), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

                $db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY')->update([
                    'field_schema' => json_encode($this->fieldsSchema(), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Log hoặc bỏ qua nếu chi nhánh chưa được cấu hình
            }
        }
    }

    public function down(): void
    {
        // No-op rollback to maintain report availability
    }

    private function uiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_segment', 'label' => 'Thị trường', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [['value' => '', 'label' => 'Select Value']]],
            ['name' => 'p_user_sale', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            ['name' => 'p_show_detail', 'label' => 'Hiển thị chi tiết', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_include_breakfast', 'label' => 'DT phòng bao gồm AS', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_market', 'label' => 'Nhóm theo thị trường', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_source', 'label' => 'Nhóm theo nguồn khách', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by', 'label' => 'Nhóm theo', 'control' => 'hidden', 'default' => 'COMPANY', 'required' => false],
        ];
    }

    private function fieldsSchema(): array
    {
        return [
            'CompanyCode' => 'string',
            'CompanyName' => 'string',
            'OccupancyRate' => 'number',
            'RoomNight' => 'number',
            'GuestQty' => 'number',
            'ActualADR' => 'number',
            'RackADR' => 'number',
            'RoomRevenue' => 'number',
            'FbRevenue' => 'number',
            'OtherRevenue' => 'number',
            'TotalRevenue' => 'number',
            'GroupHeader' => 'string',
            'GroupHeaderId' => 'string',
            'ActivityDate' => 'string',
        ];
    }

    private function companyOccupancyProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_occupancy(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(50),
    IN p_group_by VARCHAR(20),
    IN p_include_breakfast TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE DEFAULT COALESCE(p_from_date, CURRENT_DATE());
    DECLARE v_to DATE DEFAULT COALESCE(p_to_date, CURRENT_DATE());
    DECLARE v_system_date DATE DEFAULT CURRENT_DATE();
    DECLARE v_operation_date DATE;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_capacity INT DEFAULT 0;
    DECLARE v_from_past DATE;
    DECLARE v_to_past DATE;
    DECLARE v_from_future DATE;
    DECLARE v_to_future DATE;
    DECLARE v_type_group VARCHAR(20);
    DECLARE v_curr_date DATE;

    -- Chuẩn hóa p_type_group
    SET v_type_group = LOWER(COALESCE(p_group_by, 'none'));
    IF v_type_group = 'company' THEN SET v_type_group = 'none'; END IF;
    IF v_type_group = 'market' THEN SET v_type_group = 'marketsegment'; END IF;
    IF v_type_group = 'source' THEN SET v_type_group = 'sourcecode'; END IF;
    IF v_type_group = 'day_market' THEN SET v_type_group = 'daysegment'; END IF;
    IF v_type_group = 'day_source' THEN SET v_type_group = 'daysourcecode'; END IF;

    -- Kiểm tra ngày vận hành
    SELECT CAST(value AS DATE) INTO v_operation_date 
      FROM hotel_configs 
     WHERE LOWER(name) = 'operationdate' AND value IS NOT NULL AND value <> '' 
     LIMIT 1;
    IF v_operation_date IS NOT NULL AND v_from < v_operation_date THEN
        SET v_from = v_operation_date;
    END IF;

    -- Xác định ngày hệ thống theo ngày vận hành PMS.
    -- Ưu tiên cấu hình, sau đó là lịch sử Sang Ngày; chỉ cuối cùng mới dùng ngày máy chủ.
    SET v_system_date = NULL;
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

    -- Lấy tổng số phòng kinh doanh
    SELECT COUNT(*) INTO v_total_rooms 
      FROM rooms 
     WHERE COALESCE(is_internal, 0) = 0;
    IF v_total_rooms <= 0 THEN SET v_total_rooms = 1; END IF;

    -- Dựng bảng ngày ảo tmp_cal để tính Room Available từng ngày
    DROP TEMPORARY TABLE IF EXISTS tmp_cal;
    CREATE TEMPORARY TABLE tmp_cal (
        cal_date DATE PRIMARY KEY,
        ooo_rooms INT DEFAULT 0,
        room_available INT DEFAULT 0
    );

    SET v_curr_date = v_from;
    WHILE v_curr_date <= v_to DO
        INSERT INTO tmp_cal (cal_date, ooo_rooms, room_available)
        VALUES (v_curr_date, 0, v_total_rooms);
        SET v_curr_date = DATE_ADD(v_curr_date, INTERVAL 1 DAY);
    END WHILE;

    -- Cập nhật OOO từ bảng room_locks theo từng ngày
    UPDATE tmp_cal c
    SET ooo_rooms = (
        SELECT COUNT(DISTINCT rl.room_number)
          FROM room_locks rl
         WHERE rl.is_active = 1
           AND rl.lock_type = 'OOO'
           AND c.cal_date BETWEEN DATE(rl.start_date) AND DATE(rl.end_date)
    );
    UPDATE tmp_cal SET room_available = GREATEST(v_total_rooms - ooo_rooms, 0);

    -- Cập nhật từ snapshot quá khứ nếu có
    UPDATE tmp_cal c
    INNER JOIN (
        SELECT snapshot_date, SUM(room_available) AS snap_av
          FROM night_audit_room_type_snapshots
         GROUP BY snapshot_date
    ) s ON s.snapshot_date = c.cal_date
    SET c.room_available = s.snap_av
    WHERE c.cal_date < v_system_date AND s.snap_av > 0;

    SELECT COALESCE(SUM(room_available), 1) INTO v_capacity FROM tmp_cal;
    IF v_capacity <= 0 THEN SET v_capacity = 1; END IF;

    -- Bảng tạm lưu từng dòng hoạt động phòng
    DROP TEMPORARY TABLE IF EXISTS tmp_occupancy_items;
    CREATE TEMPORARY TABLE tmp_occupancy_items (
        BookingId BIGINT,
        CompanyId BIGINT,
        CompanyCode VARCHAR(50),
        CompanyName VARCHAR(255),
        MarketCode VARCHAR(50),
        MarketName VARCHAR(255),
        SourceCode VARCHAR(50),
        SourceName VARCHAR(255),
        ActivityDate DATE,
        RoomNight INT DEFAULT 0,
        GuestQty INT DEFAULT 0,
        Foc INT DEFAULT 0,
        Hu INT DEFAULT 0,
        RoomRevenue DECIMAL(15,2) DEFAULT 0,
        FbRevenue DECIMAL(15,2) DEFAULT 0,
        OtherRevenue DECIMAL(15,2) DEFAULT 0,
        OriginalAmount DECIMAL(15,2) DEFAULT 0
    );

    -- 1. Nguồn quá khứ: service_bills + room_night_bills
    IF v_from_past <= v_to_past THEN
        INSERT INTO tmp_occupancy_items
        SELECT
            b.id AS BookingId,
            c.id AS CompanyId,
            COALESCE(c.code, 'CTY0001') AS CompanyCode,
            COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
            COALESCE(m.code, '') AS MarketCode,
            COALESCE(NULLIF(m.name, ''), m.code, 'Nội địa') AS MarketName,
            COALESCE(cs.code, '') AS SourceCode,
            COALESCE(NULLIF(cs.name, ''), cs.code, 'Trực tiếp') AS SourceName,
            DATE(COALESCE(rnb.date, sb.Date)) AS ActivityDate,
            CASE WHEN sb.ServiceId = 'RM' AND COALESCE(rnb.is_room_night, 1) = 1 THEN 1 ELSE 0 END AS RoomNight,
            COALESCE((SELECT SUM(br1.adults + br1.children_qty) FROM booking_rooms br1 WHERE br1.booking_id = b.id AND br1.status NOT IN (3,100)), 1) AS GuestQty,
            CASE WHEN sb.ServiceId = 'RM' AND (rnb.rate_code = 'FOC' OR COALESCE(rnb.rate, 0) = 0) THEN 1 ELSE 0 END AS Foc,
            CASE WHEN sb.ServiceId = 'RM' AND rnb.rate_code = 'HU' THEN 1 ELSE 0 END AS Hu,
            CASE 
                WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') 
                THEN COALESCE(sb.Amount, 0) - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN COALESCE(rnb.breakfast_amount, 0) ELSE 0 END
                ELSE 0 
            END AS RoomRevenue,
            CASE 
                WHEN COALESCE(sb.DepartmentId, '') = 'FB' OR sb.ServiceId IN ('FB','OT','RB','RF') 
                THEN COALESCE(sb.Amount, 0)
                ELSE 0 
            END + CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN COALESCE(rnb.breakfast_amount, 0) ELSE 0 END AS FbRevenue,
            CASE 
                WHEN sb.ServiceId NOT IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','FB','OT','RB','RF')
                     AND COALESCE(sb.DepartmentId, '') <> 'FB'
                THEN COALESCE(sb.Amount, 0)
                ELSE 0 
            END AS OtherRevenue,
            CASE WHEN sb.ServiceId IN ('RM','EB','ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS') THEN COALESCE(NULLIF(rnb.rate, 0), sb.Amount, 0) ELSE 0 END AS OriginalAmount
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
          AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
          AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM booking_rooms bra INNER JOIN rooms rra ON rra.room_number = bra.room_number WHERE bra.booking_id = b.id AND rra.area = p_area));
    END IF;

    -- 2. Nguồn tương lai: bookings + booking_rooms
    IF v_from_future <= v_to_future THEN
        INSERT INTO tmp_occupancy_items
        SELECT
            b.id AS BookingId,
            c.id AS CompanyId,
            COALESCE(c.code, 'CTY0001') AS CompanyCode,
            COALESCE(NULLIF(c.name, ''), 'KHÁCH LẺ') AS CompanyName,
            COALESCE(m.code, '') AS MarketCode,
            COALESCE(NULLIF(m.name, ''), m.code, 'Nội địa') AS MarketName,
            COALESCE(cs.code, '') AS SourceCode,
            COALESCE(NULLIF(cs.name, ''), cs.code, 'Trực tiếp') AS SourceName,
            tc.cal_date AS ActivityDate,
            1 AS RoomNight,
            COALESCE(br.adults + br.children_qty, 1) AS GuestQty,
            CASE WHEN COALESCE(br.rate_code, '') = 'FOC' OR COALESCE(br.rate, 0) = 0 THEN 1 ELSE 0 END AS Foc,
            CASE WHEN COALESCE(br.rate_code, '') = 'HU' THEN 1 ELSE 0 END AS Hu,
            COALESCE(br.rate, 0) AS RoomRevenue,
            0 AS FbRevenue,
            0 AS OtherRevenue,
            COALESCE(br.rate, 0) AS OriginalAmount
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        INNER JOIN tmp_cal tc ON tc.cal_date >= v_from_future 
                             AND tc.cal_date <= v_to_future
                             AND tc.cal_date >= br.arrival_date 
                             AND tc.cal_date < br.departure_date
        LEFT JOIN companies c ON c.id = b.company_id
        LEFT JOIN markets m ON m.id = b.market_id
        LEFT JOIN customer_sources cs ON cs.id = b.customer_source_id
        WHERE b.deleted_at IS NULL 
          AND b.status NOT IN (3, 4)
          AND br.status NOT IN (3, 100)
          AND (COALESCE(p_company, '') = '' OR CAST(b.company_id AS CHAR) = p_company OR c.code = p_company)
          AND (COALESCE(p_segment, '') = '' OR CAST(b.market_id AS CHAR) = p_segment OR m.code = p_segment)
          AND (COALESCE(p_user_sale, '') = '' OR b.sales_person = p_user_sale OR b.created_by = p_user_sale)
          AND (COALESCE(p_area, '') = '' OR EXISTS (SELECT 1 FROM rooms rra WHERE rra.room_number = br.room_number AND rra.area = p_area));
    END IF;

    -- 3. Tổng hợp và gom nhóm theo v_type_group
    SELECT
        g.CompanyCode,
        g.CompanyName,
        ROUND(SUM(g.RoomNight) * 100.0 / v_capacity, 2) AS OccupancyRate,
        CAST(SUM(g.RoomNight) AS SIGNED) AS RoomNight,
        CAST(SUM(g.GuestQty) AS SIGNED) AS GuestQty,
        CASE WHEN SUM(g.RoomNight) > 0 THEN ROUND(SUM(g.RoomRevenue) / SUM(g.RoomNight), 0) ELSE 0 END AS ActualADR,
        CASE WHEN SUM(g.RoomNight - g.Foc - g.Hu) > 0 THEN ROUND(SUM(g.RoomRevenue) / SUM(g.RoomNight - g.Foc - g.Hu), 0) ELSE 0 END AS RackADR,
        ROUND(SUM(g.RoomRevenue), 0) AS RoomRevenue,
        ROUND(SUM(g.FbRevenue), 0) AS FbRevenue,
        ROUND(SUM(g.OtherRevenue), 0) AS OtherRevenue,
        ROUND(SUM(g.RoomRevenue + g.FbRevenue + g.OtherRevenue), 0) AS TotalRevenue,
        CAST(SUM(g.Foc) AS SIGNED) AS Foc,
        CAST(SUM(g.Hu) AS SIGNED) AS Hu,
        g.GroupHeader,
        g.GroupHeaderId,
        MIN(g.ActivityDate) AS ActivityDate,
        v_capacity AS TotalRoomAvailable
    FROM (
        SELECT
            CompanyCode,
            CompanyName,
            RoomNight,
            GuestQty,
            Foc,
            Hu,
            RoomRevenue,
            FbRevenue,
            OtherRevenue,
            OriginalAmount,
            ActivityDate,
            CASE v_type_group
                WHEN 'date' THEN DATE_FORMAT(ActivityDate, '%d/%m/%Y')
                WHEN 'marketsegment' THEN COALESCE(NULLIF(MarketName, ''), MarketCode, 'Nội địa')
                WHEN 'sourcecode' THEN COALESCE(NULLIF(SourceName, ''), SourceCode, 'Trực tiếp')
                WHEN 'daysegment' THEN CONCAT(DATE_FORMAT(ActivityDate, '%d/%m/%Y'), ' - ', COALESCE(NULLIF(MarketName, ''), MarketCode, 'Nội địa'))
                WHEN 'daysourcecode' THEN CONCAT(DATE_FORMAT(ActivityDate, '%d/%m/%Y'), ' - ', COALESCE(NULLIF(SourceName, ''), SourceCode, 'Trực tiếp'))
                ELSE 'BÁO CÁO CÔNG SUẤT CÔNG TY'
            END AS GroupHeader,
            CASE v_type_group
                WHEN 'date' THEN DATE_FORMAT(ActivityDate, '%Y-%m-%d')
                WHEN 'marketsegment' THEN MarketCode
                WHEN 'sourcecode' THEN SourceCode
                WHEN 'daysegment' THEN CONCAT(DATE_FORMAT(ActivityDate, '%Y-%m-%d'), '_', MarketCode)
                WHEN 'daysourcecode' THEN CONCAT(DATE_FORMAT(ActivityDate, '%Y-%m-%d'), '_', SourceCode)
                ELSE 'ALL'
            END AS GroupHeaderId
        FROM tmp_occupancy_items
    ) g
    GROUP BY g.GroupHeaderId, g.GroupHeader, g.CompanyCode, g.CompanyName, 
             CASE WHEN v_type_group IN ('date', 'daysegment', 'daysourcecode') THEN g.ActivityDate ELSE NULL END
    ORDER BY 
        CASE WHEN v_type_group IN ('date', 'daysegment', 'daysourcecode') THEN MIN(g.ActivityDate) END ASC,
        g.GroupHeader ASC,
        TotalRevenue DESC,
        g.CompanyName ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_cal;
    DROP TEMPORARY TABLE IF EXISTS tmp_occupancy_items;
END;
SQL;
    }
};
