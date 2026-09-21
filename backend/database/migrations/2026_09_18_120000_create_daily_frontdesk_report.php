<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTIONS = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
    private const SOURCE = 'RPT_DAILY_FRONTDESK';
    private const REPORT = 'DAILY_FRONTDESK';
    private const TEMPLATE = 'DAILY_FRONTDESK_REFERENCE';

    public function up(): void
    {
        $visited = [];

        foreach (self::CONNECTIONS as $connectionName) {
            try {
                $connection = DB::connection($connectionName);
                if ($connection->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $connection->getDatabaseName();
                if (isset($visited[$database])) {
                    continue;
                }
                $visited[$database] = true;

                $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_daily_frontdesk');
                $connection->unprepared($this->procedureSql());
                $this->syncReportConfiguration($connectionName);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_daily_frontdesk(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_cur_date DATE;

    IF p_from_date IS NULL OR p_from_date = '' THEN
        SET v_from = CURDATE() - INTERVAL 1 DAY;
    ELSEIF INSTR(p_from_date, '/') > 0 THEN
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%d/%m/%Y');
    ELSE
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%Y-%m-%d');
    END IF;

    IF p_to_date IS NULL OR p_to_date = '' THEN
        SET v_to = v_from;
    ELSEIF INSTR(p_to_date, '/') > 0 THEN
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%d/%m/%Y');
    ELSE
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%Y-%m-%d');
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_rooms;
    CREATE TEMPORARY TABLE tmp_frontdesk_rooms (
        room_id VARCHAR(50) NOT NULL,
        arrival_date DATE NOT NULL,
        checkout_date DATE NOT NULL,
        actual_days INT NOT NULL,
        is_day_use TINYINT NOT NULL DEFAULT 0,
        breakfast TINYINT NOT NULL DEFAULT 0,
        adults INT NOT NULL DEFAULT 0,
        room_status INT NOT NULL DEFAULT 0,
        segment_order INT NOT NULL,
        segment VARCHAR(100) NOT NULL,
        PRIMARY KEY (room_id),
        INDEX idx_frontdesk_arrival (arrival_date),
        INDEX idx_frontdesk_checkout (checkout_date),
        INDEX idx_frontdesk_segment (segment_order)
    );

    INSERT INTO tmp_frontdesk_rooms (
        room_id, arrival_date, checkout_date, actual_days, is_day_use,
        breakfast, adults, room_status, segment_order, segment
    )
    SELECT
        br.id,
        br.arrival_date,
        DATE_ADD(br.arrival_date, INTERVAL br.ActutalNumOfDays DAY),
        br.ActutalNumOfDays,
        CASE WHEN br.is_day_use = 1 OR br.ActutalNumOfDays = 0 THEN 1 ELSE 0 END,
        COALESCE(br.breakfast, 0),
        COALESCE(br.adults, 0),
        br.status,
        CASE
            WHEN UPPER(COALESCE(m.code, '')) IN ('TA', 'FOC', 'VOUCHER')
              OR LOWER(COALESCE(m.name, '')) IN ('travel agent', 'foc', 'voucher') THEN 1
            WHEN UPPER(COALESCE(m.code, '')) IN ('OTA', 'ONLINE TRAVEL AGENT')
              OR LOWER(COALESCE(m.name, '')) = 'online travel agent' THEN 2
            WHEN UPPER(COALESCE(m.code, '')) IN ('CORP', 'CORPORATE')
              OR LOWER(COALESCE(m.name, '')) = 'corporate' THEN 3
            ELSE 4
        END,
        CASE
            WHEN UPPER(COALESCE(m.code, '')) IN ('TA', 'FOC', 'VOUCHER')
              OR LOWER(COALESCE(m.name, '')) IN ('travel agent', 'foc', 'voucher') THEN 'Khách TA'
            WHEN UPPER(COALESCE(m.code, '')) IN ('OTA', 'ONLINE TRAVEL AGENT')
              OR LOWER(COALESCE(m.name, '')) = 'online travel agent' THEN 'Khách OTA'
            WHEN UPPER(COALESCE(m.code, '')) IN ('CORP', 'CORPORATE')
              OR LOWER(COALESCE(m.name, '')) = 'corporate' THEN 'Khách Corp'
            ELSE 'Khách Walk-in, FIT, Fanpage'
        END
    FROM booking_rooms AS br
    INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN markets AS m ON m.id = COALESCE(b.market_id, c.market_id)
    LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
    LEFT JOIN rooms AS r ON r.room_number = br.room_number
    WHERE br.deleted_at IS NULL
      AND br.status IN (0, 1, 2, 100)
      AND NOT (br.status = 100 AND br.arrival_date = br.CheckoutDate)
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
      AND COALESCE(r.is_internal, 0) = 0
      AND COALESCE(rs.is_availability, 1) = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_daily_frontdesk;
    CREATE TEMPORARY TABLE tmp_daily_frontdesk (
        `Date` DATE NOT NULL,
        `DateFormatted` VARCHAR(20) NOT NULL,
        `SegmentOrder` INT NOT NULL,
        `Segment` VARCHAR(100) NOT NULL,
        `CheckinRoom` INT NOT NULL DEFAULT 0,
        `CheckoutRoom` INT NOT NULL DEFAULT 0,
        `InhouseRoom` INT NOT NULL DEFAULT 0,
        `DayUseRoom` INT NOT NULL DEFAULT 0,
        `RawDayUseRoom` INT NOT NULL DEFAULT 0,
        `BreakfastGuestNum` INT NOT NULL DEFAULT 0,
        `NoBreakfastGuestNum` INT NOT NULL DEFAULT 0,
        `Notes` VARCHAR(255) NOT NULL DEFAULT '',
        `CustomerFeedback` VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`Date`, `SegmentOrder`)
    );

    SET v_cur_date = v_from;
    WHILE v_cur_date <= v_to DO
        INSERT INTO tmp_daily_frontdesk (`Date`, `DateFormatted`, `SegmentOrder`, `Segment`)
        VALUES
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 1, 'Khách TA'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 2, 'Khách OTA'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 3, 'Khách Corp'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 4, 'Khách Walk-in, FIT, Fanpage');
        SET v_cur_date = DATE_ADD(v_cur_date, INTERVAL 1 DAY);
    END WHILE;

    UPDATE tmp_daily_frontdesk AS t
    INNER JOIN (
        SELECT arrival_date AS report_date, segment_order, COUNT(DISTINCT room_id) AS room_count
        FROM tmp_frontdesk_rooms
        WHERE arrival_date BETWEEN v_from AND v_to
        GROUP BY arrival_date, segment_order
    ) AS x ON x.report_date = t.`Date` AND x.segment_order = t.`SegmentOrder`
    SET t.CheckinRoom = x.room_count;

    UPDATE tmp_daily_frontdesk AS t
    INNER JOIN (
        SELECT checkout_date AS report_date, segment_order, COUNT(DISTINCT room_id) AS room_count
        FROM tmp_frontdesk_rooms
        WHERE checkout_date BETWEEN v_from AND v_to
          AND room_status IN (0, 1, 2)
        GROUP BY checkout_date, segment_order
    ) AS x ON x.report_date = t.`Date` AND x.segment_order = t.`SegmentOrder`
    SET t.CheckoutRoom = x.room_count;

    UPDATE tmp_daily_frontdesk AS t
    INNER JOIN (
        SELECT arrival_date AS report_date, segment_order, COUNT(DISTINCT room_id) AS room_count
        FROM tmp_frontdesk_rooms
        WHERE arrival_date BETWEEN v_from AND v_to
          AND (is_day_use = 1 OR actual_days = 0)
        GROUP BY arrival_date, segment_order
    ) AS x ON x.report_date = t.`Date` AND x.segment_order = t.`SegmentOrder`
    SET t.RawDayUseRoom = x.room_count,
        t.DayUseRoom = x.room_count;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_occupancy;
    CREATE TEMPORARY TABLE tmp_frontdesk_occupancy (
        stay_date DATE NOT NULL,
        segment_order INT NOT NULL,
        room_count INT NOT NULL,
        PRIMARY KEY (stay_date, segment_order)
    );

    INSERT INTO tmp_frontdesk_occupancy (stay_date, segment_order, room_count)
    SELECT
        rnb.date,
        fr.segment_order,
        COUNT(DISTINCT fr.room_id)
    FROM room_night_bills AS rnb
    INNER JOIN service_bills AS sb ON sb.Ma = rnb.bill_id AND sb.ServiceId = 'RM' AND sb.Edit = 0
    INNER JOIN tmp_frontdesk_rooms AS fr ON fr.room_id = sb.RentalRoomId1
    WHERE rnb.is_room_night = 1
      AND rnb.date BETWEEN DATE_SUB(v_from, INTERVAL 1 DAY) AND v_to
      AND rnb.date >= fr.arrival_date
      AND rnb.date < fr.checkout_date
      AND NOT EXISTS (
          SELECT 1
          FROM late_checkins AS lc
          WHERE lc.booking_room_id = fr.room_id
            AND DATE(lc.actual_arrival_date) = rnb.date
            AND COALESCE(lc.status, 0) <> 3
      )
    GROUP BY rnb.date, fr.segment_order;

    UPDATE tmp_daily_frontdesk AS t
    LEFT JOIN tmp_frontdesk_occupancy AS o
      ON o.stay_date = DATE_SUB(t.`Date`, INTERVAL 1 DAY)
     AND o.segment_order = t.`SegmentOrder`
    SET t.InhouseRoom = COALESCE(o.room_count, 0) - t.CheckoutRoom + t.RawDayUseRoom;

    UPDATE tmp_daily_frontdesk
    SET DayUseRoom = CheckinRoom + InhouseRoom - RawDayUseRoom;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_dates;
    CREATE TEMPORARY TABLE tmp_frontdesk_dates (
        report_date DATE PRIMARY KEY
    );
    INSERT INTO tmp_frontdesk_dates (report_date)
    SELECT DISTINCT `Date` FROM tmp_daily_frontdesk;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_breakfast;
    CREATE TEMPORARY TABLE tmp_frontdesk_breakfast (
        report_date DATE NOT NULL,
        segment_order INT NOT NULL,
        breakfast_count INT NOT NULL DEFAULT 0,
        no_breakfast_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (report_date, segment_order)
    );

    INSERT INTO tmp_frontdesk_breakfast (report_date, segment_order, breakfast_count, no_breakfast_count)
    SELECT
        d.report_date,
        fr.segment_order,
        SUM(CASE
            WHEN g.id IS NULL THEN CASE WHEN fr.breakfast = 1 THEN fr.adults ELSE 0 END
            ELSE CASE WHEN COALESCE(g.breakfast, fr.breakfast, 0) = 1 THEN 1 ELSE 0 END
        END),
        SUM(CASE
            WHEN g.id IS NULL THEN CASE WHEN fr.breakfast = 1 THEN 0 ELSE fr.adults END
            ELSE CASE WHEN COALESCE(g.breakfast, fr.breakfast, 0) = 1 THEN 0 ELSE 1 END
        END)
    FROM tmp_frontdesk_dates AS d
    INNER JOIN tmp_frontdesk_rooms AS fr
        ON fr.arrival_date <= DATE_ADD(d.report_date, INTERVAL 1 DAY)
       AND fr.checkout_date >= DATE_ADD(d.report_date, INTERVAL 1 DAY)
       AND fr.room_status IN (0, 1, 2, 100)
    LEFT JOIN booking_room_guests AS g
        ON g.booking_room_id = fr.room_id
       AND COALESCE(g.status, 0) <> 3
       AND (g.actual_arrival_date IS NULL OR g.actual_arrival_date <= DATE_ADD(d.report_date, INTERVAL 1 DAY))
       AND (g.actual_checkout_date IS NULL OR g.actual_checkout_date >= DATE_ADD(d.report_date, INTERVAL 1 DAY))
    GROUP BY d.report_date, fr.segment_order;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_children;
    CREATE TEMPORARY TABLE tmp_frontdesk_children (
        child_id VARCHAR(50) NOT NULL,
        room_id VARCHAR(50) NOT NULL,
        PRIMARY KEY (child_id, room_id)
    );

    INSERT IGNORE INTO tmp_frontdesk_children (child_id, room_id)
    SELECT bc.id, bc.booking_room_id
    FROM booking_children AS bc
    WHERE bc.booking_room_id IS NOT NULL
      AND COALESCE(bc.child_status, 0) <> 3;

    INSERT IGNORE INTO tmp_frontdesk_children (child_id, room_id)
    SELECT bc.id, brc.booking_room_id
    FROM booking_children AS bc
    INNER JOIN booking_room_children AS brc ON brc.booking_child_id = bc.id AND COALESCE(brc.status, 1) <> 3
    WHERE COALESCE(bc.child_status, 0) <> 3;

    INSERT INTO tmp_frontdesk_breakfast (report_date, segment_order, breakfast_count, no_breakfast_count)
    SELECT
        d.report_date,
        fr.segment_order,
        SUM(CASE WHEN COALESCE(bfd.breakfast, 0) = 1 THEN 1 ELSE 0 END),
        SUM(CASE WHEN COALESCE(bfd.breakfast, 0) = 1 THEN 0 ELSE 1 END)
    FROM tmp_frontdesk_dates AS d
    INNER JOIN tmp_frontdesk_rooms AS fr
        ON fr.arrival_date <= DATE_ADD(d.report_date, INTERVAL 1 DAY)
       AND fr.checkout_date >= DATE_ADD(d.report_date, INTERVAL 1 DAY)
       AND fr.room_status IN (0, 1, 2, 100)
    INNER JOIN tmp_frontdesk_children AS fc ON fc.room_id = fr.room_id
    INNER JOIN booking_children AS bc ON bc.id = fc.child_id AND COALESCE(bc.child_status, 0) <> 3
    LEFT JOIN booking_child_breakfast_details AS bfd
        ON bfd.booking_child_id = bc.id
       AND bfd.service_date = DATE_ADD(d.report_date, INTERVAL 1 DAY)
    GROUP BY d.report_date, fr.segment_order
    ON DUPLICATE KEY UPDATE
        breakfast_count = breakfast_count + VALUES(breakfast_count),
        no_breakfast_count = no_breakfast_count + VALUES(no_breakfast_count);

    UPDATE tmp_daily_frontdesk AS t
    LEFT JOIN tmp_frontdesk_breakfast AS b
      ON b.report_date = t.`Date` AND b.segment_order = t.`SegmentOrder`
    SET t.BreakfastGuestNum = COALESCE(b.breakfast_count, 0),
        t.NoBreakfastGuestNum = COALESCE(b.no_breakfast_count, 0);

    SELECT
        `Date`, `DateFormatted`, `SegmentOrder`, `Segment`,
        `CheckinRoom`, `CheckoutRoom`, `InhouseRoom`, `DayUseRoom`,
        `BreakfastGuestNum`, `NoBreakfastGuestNum`, `Notes`, `CustomerFeedback`
    FROM tmp_daily_frontdesk
    ORDER BY `Date`, `SegmentOrder`;

    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_children;
    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_breakfast;
    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_dates;
    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_occupancy;
    DROP TEMPORARY TABLE IF EXISTS tmp_daily_frontdesk;
    DROP TEMPORARY TABLE IF EXISTS tmp_frontdesk_rooms;
END
SQL;
    }

    private function syncReportConfiguration(string $connectionName): void
    {
        $now = now();
        $db = DB::connection($connectionName);
        $database = $db->getDatabaseName();

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'varchar(20)', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'varchar(20)', 'position' => 2, 'required' => true],
        ];
        $fields = collect(['Date', 'DateFormatted', 'SegmentOrder', 'Segment', 'CheckinRoom', 'CheckoutRoom', 'InhouseRoom', 'DayUseRoom', 'BreakfastGuestNum', 'NoBreakfastGuestNum', 'Notes', 'CustomerFeedback'])
            ->map(fn (string $name) => [
                'name' => $name,
                'type' => in_array($name, ['SegmentOrder', 'CheckinRoom', 'CheckoutRoom', 'InhouseRoom', 'DayUseRoom', 'BreakfastGuestNum', 'NoBreakfastGuestNum'], true) ? 'integer' : 'string',
                'nullable' => ! in_array($name, ['Date', 'DateFormatted', 'SegmentOrder', 'Segment'], true),
            ])->all();
        $defaults = [
            'p_from_date' => now()->subDay()->toDateString(),
            'p_to_date' => now()->subDay()->toDateString(),
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo lễ tân hằng ngày',
            'description' => 'MySQL chuyển đổi từ legacy sp_275; thống kê phòng và ăn sáng theo phân khúc.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_daily_frontdesk',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/daily_frontdesk_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo thống kê lễ tân',
            'name' => $definition['name'],
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
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
            'version' => '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$yesterday', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$yesterday', 'required' => true],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo lễ tân hằng ngày',
            'group' => 'Báo cáo thống kê lễ tân',
            'description' => 'Báo cáo phòng và suất ăn sáng theo 4 phân khúc khách hàng theo legacy sp_275.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 46,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 46,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        foreach (self::CONNECTIONS as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }
                $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
                $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
                if ($reportId) {
                    $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                    $db->table('report_definitions')->where('id', $reportId)->delete();
                }
                if ($templateId) {
                    $db->table('templates')->where('id', $templateId)->delete();
                }
                $db->table('report_data_sources')->where('code', self::SOURCE)->delete();
                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_daily_frontdesk');
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
};
