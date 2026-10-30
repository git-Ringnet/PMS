<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_ROOM_FORECAST';
    private const REPORT = 'ROOM_FORECAST';
    private const TEMPLATE = 'ROOM_FORECAST_REFERENCE';
    private const PROCEDURE = 'rpt_room_forecast';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach ($this->targetConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $db->unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
            // MySQL stores the session sql_mode with the procedure. Its per-room correlated
            // subqueries inside SUM() are rejected by ONLY_FULL_GROUP_BY (Laravel strict mode).
            $sqlMode = $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
            $db->statement("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', '')");
            try {
                $db->unprepared($this->procedureSql());
            } finally {
                $db->statement('SET SESSION sql_mode = ?', [$sqlMode]);
            }
            $this->syncConfiguration($db, $database);
        }
    }

    public function down(): void
    {
        $visitedDatabases = [];

        foreach ($this->targetConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
            if ($reportId) {
                $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                $db->table('report_definitions')->where('id', $reportId)->delete();
            }
            $db->table('templates')->where('report', self::TEMPLATE)->delete();
            $db->table('report_data_sources')->where('code', self::SOURCE)->delete();
            $db->unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_room_forecast(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(50),
    IN p_branch VARCHAR(20),
    IN p_room_type VARCHAR(20),
    IN p_revenue_detail TINYINT,
    IN p_include_breakfast TINYINT,
    IN p_area VARCHAR(100)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_swap DATE;
    DECLARE v_op_date DATE DEFAULT NULL;
    DECLARE v_system_date DATE DEFAULT NULL;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_breakfast_adult_rate DECIMAL(15,2) DEFAULT 0;
    DECLARE v_breakfast_child_rate DECIMAL(15,2) DEFAULT 0;
    DECLARE v_avg_rate_inc_other TINYINT DEFAULT 0;

    SET v_from = COALESCE(p_from_date, CURRENT_DATE());
    SET v_to = COALESCE(p_to_date, v_from);
    IF v_to < v_from THEN
        SET v_swap = v_from;
        SET v_from = v_to;
        SET v_to = v_swap;
    END IF;
    SET p_include_breakfast = COALESCE(p_include_breakfast, 1);

    SELECT COALESCE(
        MAX(CASE
            WHEN LOWER(name) = 'operationdate' AND TRIM(COALESCE(value, '')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                THEN STR_TO_DATE(LEFT(TRIM(value), 10), '%Y-%m-%d')
            WHEN LOWER(name) = 'operationdate' AND TRIM(COALESCE(value, '')) REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}'
                THEN STR_TO_DATE(LEFT(TRIM(value), 10), '%d/%m/%Y')
            ELSE NULL
        END),
        (SELECT MIN(DATE(system_date)) FROM system_date_rolls)
    ) INTO v_op_date
    FROM hotel_configs;

    IF v_op_date IS NOT NULL AND v_from < v_op_date THEN
        SET v_from = v_op_date;
        IF v_to < v_from THEN
            SET v_to = v_from;
        END IF;
    END IF;

    SELECT COALESCE(
        MAX(CASE
            WHEN LOWER(name) IN ('systemdate', 'system_date') AND TRIM(COALESCE(value, '')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                THEN STR_TO_DATE(LEFT(TRIM(value), 10), '%Y-%m-%d')
            WHEN LOWER(name) IN ('systemdate', 'system_date') AND TRIM(COALESCE(value, '')) REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}'
                THEN STR_TO_DATE(LEFT(TRIM(value), 10), '%d/%m/%Y')
            ELSE NULL
        END),
        (SELECT DATE(sdr.system_date) FROM system_date_rolls AS sdr ORDER BY sdr.id DESC LIMIT 1),
        CURRENT_DATE()
    ) INTO v_system_date
    FROM hotel_configs;

    SELECT COALESCE(MAX(CASE
        WHEN LOWER(name) = 'averageroomrateincludedothersroomrevenue'
         AND LOWER(TRIM(COALESCE(value, ''))) IN ('1', 'true', 'yes')
        THEN 1 ELSE 0
    END), 0)
      INTO v_avg_rate_inc_other
      FROM hotel_configs;

    SET v_breakfast_adult_rate = COALESCE((
        SELECT hs.breakfast_adult_rate
        FROM hotel_settings hs
        ORDER BY hs.id
        LIMIT 1
    ), 0);
    SET v_breakfast_child_rate = COALESCE((
        SELECT hs.breakfast_child_rate
        FROM hotel_settings hs
        ORDER BY hs.id
        LIMIT 1
    ), 0);

    SELECT COUNT(*)
      INTO v_total_rooms
    FROM rooms r
    WHERE COALESCE(r.is_internal, 0) = 0
      AND COALESCE(r.room_number, '') NOT LIKE '0%'
      AND (COALESCE(p_area, '') = '' OR r.area = p_area)
      AND (
          COALESCE(p_room_type, '') = ''
          OR CAST(r.room_class_id AS CHAR) = p_room_type
          OR EXISTS (
              SELECT 1
              FROM room_classes rc
              WHERE rc.id = r.room_class_id
                AND rc.code = p_room_type
          )
      );

    WITH RECURSIVE calendar AS (
        SELECT v_from AS dt
        UNION ALL
        SELECT dt + INTERVAL 1 DAY
        FROM calendar
        WHERE dt < v_to
    ), eligible_rooms AS (
        SELECT
            br.id,
            br.booking_id,
            COALESCE(br.status, 0) AS status,
            DATE(br.arrival_date) AS arrival_date,
            DATE(br.departure_date) AS departure_date,
            LEAST(
                DATE(br.arrival_date),
                DATE(COALESCE(br.actual_arrival_date, br.arrival_date)),
                COALESCE((
                    SELECT MIN(LEAST(DATE(prev.arrival_date), DATE(COALESCE(prev.actual_arrival_date, prev.arrival_date))))
                    FROM booking_rooms AS prev
                    WHERE prev.booking_id = br.booking_id
                      AND prev.move_room = br.id
                      AND prev.deleted_at IS NULL
                ), DATE(br.arrival_date))
            ) AS true_arrival_date,
            COALESCE(br.is_day_use, 0) AS is_day_use,
            CASE
                WHEN DATE(br.arrival_date) = DATE(br.departure_date)
                 AND COALESCE(br.status, 0) IN (1, 2)
                 AND EXISTS (
                     SELECT 1
                     FROM service_bills sb0
                     INNER JOIN room_night_bills rnb0 ON rnb0.bill_id = sb0.Ma
                     WHERE COALESCE(NULLIF(TRIM(sb0.RentalRoomId2), ''), NULLIF(TRIM(sb0.RentalRoomId1), '')) = br.id
                       AND COALESCE(sb0.Edit, 0) = 0
                       AND COALESCE(sb0.Status, 1) <> 3
                       AND UPPER(TRIM(sb0.ServiceId)) = 'RM'
                       AND COALESCE(rnb0.is_room_night, 1) = 1
                       AND DATE(COALESCE(rnb0.date, sb0.Date)) = DATE(br.arrival_date)
                 )
                THEN 1
                ELSE 0
            END AS has_same_day_rm_night,
            br.rate,
            br.rate_code,
            br.breakfast,
            br.extra_bed_qty,
            br.extra_bed_rate,
            br.adults,
            br.children_qty,
            COALESCE(r.is_internal, 0) AS room_internal,
            COALESCE(r.room_number, '') AS room_number,
            b.breakfast_included AS booking_breakfast_included
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        LEFT JOIN registration_statuses rs
            ON (rs.booking_status_id = b.registration_status_id OR rs.id = b.registration_status_id)
        LEFT JOIN rooms r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND COALESCE(rs.is_availability, 1) = 1
          AND COALESCE(br.status, 0) IN (0, 1, 2, 100)
          AND (COALESCE(p_user, '') = '' OR b.sales_person = p_user OR b.created_by = p_user)
          AND (
              COALESCE(p_room_type, '') = ''
              OR CAST(COALESCE(r.room_class_id, br.room_class_id) AS CHAR) = p_room_type
              OR EXISTS (
                  SELECT 1
                  FROM room_classes rc
                  WHERE rc.id = COALESCE(r.room_class_id, br.room_class_id)
                    AND rc.code = p_room_type
              )
          )
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND (COALESCE(p_area, '') = '' OR r.area = p_area)
    ), posted_bills AS (
        SELECT
            DATE(COALESCE(rnb.date, sb.Date)) AS bill_date,
            COALESCE(SUM(CASE
                WHEN UPPER(TRIM(sb.ServiceId)) = 'RM'
                THEN COALESCE(sb.Amount, 0) - CASE
                    WHEN p_include_breakfast = 0
                    THEN COALESCE(
                        NULLIF(rnb.breakfast_amount, 0),
                        bfd.bf_amount,
                        0
                    )
                    ELSE 0
                END
                ELSE 0
            END), 0) AS posted_rm,
            COALESCE(SUM(CASE
                WHEN UPPER(TRIM(sb.ServiceId)) = 'EB' THEN COALESCE(sb.Amount, 0)
                ELSE 0
            END), 0) AS posted_eb,
            COALESCE(SUM(CASE
                WHEN UPPER(TRIM(sb.ServiceId)) IN ('ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','EI')
                THEN COALESCE(sb.Amount, 0)
                ELSE 0
            END), 0) AS posted_er
        FROM service_bills sb
        LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma
        LEFT JOIN (
            SELECT d.BillServiceId, SUM(d.Amount) AS bf_amount
            FROM service_bill_details d
            WHERE UPPER(TRIM(d.ServiceId)) = 'BF'
            GROUP BY d.BillServiceId
        ) AS bfd ON bfd.BillServiceId = sb.Ma
        LEFT JOIN booking_rooms br ON br.id = COALESCE(NULLIF(TRIM(sb.RentalRoomId2), ''), NULLIF(TRIM(sb.RentalRoomId1), ''))
        LEFT JOIN bookings b ON b.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0), NULLIF(sb.RegisterId1, 0))
        LEFT JOIN registration_statuses rs
            ON (rs.booking_status_id = b.registration_status_id OR rs.id = b.registration_status_id)
        LEFT JOIN rooms r ON r.room_number = br.room_number
        WHERE COALESCE(sb.Edit, 0) = 0
          AND COALESCE(sb.Status, 1) <> 3
          AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN v_from AND v_to
          AND (b.id IS NULL OR (b.deleted_at IS NULL AND b.status NOT IN (3, 4) AND COALESCE(rs.is_availability, 1) = 1))
          AND (
              COALESCE(p_user, '') = ''
              OR b.sales_person = p_user
              OR b.created_by = p_user
              OR COALESCE(NULLIF(sb.Username, ''), NULLIF(sb.CreatedUser, '')) = p_user
          )
          AND (
              COALESCE(p_room_type, '') = ''
              OR CAST(COALESCE(r.room_class_id, br.room_class_id) AS CHAR) = p_room_type
              OR EXISTS (
                  SELECT 1
                  FROM room_classes rc
                  WHERE rc.id = COALESCE(r.room_class_id, br.room_class_id)
                    AND rc.code = p_room_type
              )
          )
          AND (COALESCE(p_area, '') = '' OR r.area = p_area)
        GROUP BY DATE(COALESCE(rnb.date, sb.Date))
    ), daily_base AS (
        SELECT
            c.dt AS report_date,
            COUNT(DISTINCT CASE
                WHEN er.status IN (0, 1, 2) AND er.departure_date = c.dt THEN er.id
            END) AS DepRooms,
            COALESCE(SUM(CASE
                WHEN er.status IN (0, 1, 2) AND er.departure_date = c.dt
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS DepAdult,
            COUNT(DISTINCT CASE
                WHEN er.status IN (0, 1, 2) AND er.true_arrival_date = c.dt THEN er.id
            END) AS ArrRooms,
            COALESCE(SUM(CASE
                WHEN er.status IN (0, 1, 2) AND er.true_arrival_date = c.dt
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS ArrAdult,
            COUNT(DISTINCT CASE
                WHEN (c.dt >= er.arrival_date AND c.dt < er.departure_date)
                  OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND (er.is_day_use = 1 OR er.has_same_day_rm_night = 1))
                THEN er.id
            END) AS OccRooms,
            COALESCE(SUM(CASE
                WHEN (c.dt >= er.arrival_date AND c.dt < er.departure_date)
                  OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND (er.is_day_use = 1 OR er.has_same_day_rm_night = 1))
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS OccAdult,
            COUNT(DISTINCT CASE
                WHEN ((c.dt >= er.arrival_date AND c.dt < er.departure_date)
                   OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND (er.is_day_use = 1 OR er.has_same_day_rm_night = 1)))
                 AND (er.room_internal = 1 OR UPPER(COALESCE(er.rate_code, '')) = 'HU')
                THEN er.id
            END) AS HouseUse,
            COUNT(DISTINCT CASE
                WHEN ((c.dt >= er.arrival_date AND c.dt < er.departure_date)
                   OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND (er.is_day_use = 1 OR er.has_same_day_rm_night = 1)))
                 AND COALESCE(er.room_internal, 0) = 0
                 AND UPPER(COALESCE(er.rate_code, '')) <> 'HU'
                 AND (
                     COALESCE(er.rate, 0) = 0
                     OR UPPER(COALESCE(er.rate_code, '')) LIKE 'FOC%'
                 )
                THEN er.id
            END) AS FOCAll,
            COALESCE(pb.posted_rm, 0) + COALESCE(SUM(CASE
                WHEN (c.dt >= v_system_date OR COALESCE(pb.posted_rm, 0) + COALESCE(pb.posted_eb, 0) + COALESCE(pb.posted_er, 0) = 0)
                 AND ((c.dt >= er.arrival_date AND c.dt < er.departure_date)
                   OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND er.is_day_use = 1))
                 AND NOT EXISTS (
                     SELECT 1
                     FROM service_bills sb1
                     LEFT JOIN room_night_bills rnb1 ON rnb1.bill_id = sb1.Ma
                     WHERE COALESCE(NULLIF(TRIM(sb1.RentalRoomId2), ''), NULLIF(TRIM(sb1.RentalRoomId1), '')) = er.id
                       AND COALESCE(sb1.Edit, 0) = 0
                       AND COALESCE(sb1.Status, 1) <> 3
                       AND UPPER(TRIM(sb1.ServiceId)) = 'RM'
                       AND DATE(COALESCE(rnb1.date, sb1.Date)) = c.dt
                 )
                THEN GREATEST(
                    COALESCE(
                        (
                            SELECT SUM(brs.total_amount)
                            FROM booking_room_services brs
                            WHERE brs.booking_room_id = er.id
                              AND UPPER(TRIM(brs.service_code)) = 'RM'
                              AND DATE(brs.service_date) = c.dt
                              AND COALESCE(brs.is_posted, 0) = 0
                              AND brs.deleted_at IS NULL
                        ),
                        CASE
                            WHEN UPPER(COALESCE(er.rate_code, '')) IN ('FOC', 'HU') THEN 0
                            ELSE COALESCE(er.rate, 0)
                        END
                    ) - CASE
                        WHEN p_include_breakfast = 0
                         AND (COALESCE(er.breakfast, 0) = 1 OR COALESCE(er.booking_breakfast_included, 0) = 1)
                        THEN COALESCE(er.adults, 0) * v_breakfast_adult_rate + COALESCE(er.children_qty, 0) * v_breakfast_child_rate
                        ELSE 0
                    END,
                    0
                )
                ELSE 0
            END), 0) AS RM,
            COALESCE(pb.posted_eb, 0) + COALESCE(SUM(CASE
                WHEN (c.dt >= v_system_date OR COALESCE(pb.posted_rm, 0) + COALESCE(pb.posted_eb, 0) + COALESCE(pb.posted_er, 0) = 0)
                 AND ((c.dt >= er.arrival_date AND c.dt < er.departure_date)
                   OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND er.is_day_use = 1))
                 AND NOT EXISTS (
                     SELECT 1
                     FROM service_bills sb2
                     LEFT JOIN room_night_bills rnb2 ON rnb2.bill_id = sb2.Ma
                     WHERE COALESCE(NULLIF(TRIM(sb2.RentalRoomId2), ''), NULLIF(TRIM(sb2.RentalRoomId1), '')) = er.id
                       AND COALESCE(sb2.Edit, 0) = 0
                       AND COALESCE(sb2.Status, 1) <> 3
                       AND UPPER(TRIM(sb2.ServiceId)) = 'EB'
                       AND DATE(COALESCE(rnb2.date, sb2.Date)) = c.dt
                 )
                THEN COALESCE(
                    (
                        SELECT SUM(brs.total_amount)
                        FROM booking_room_services brs
                        WHERE brs.booking_room_id = er.id
                          AND UPPER(TRIM(brs.service_code)) = 'EB'
                          AND DATE(brs.service_date) = c.dt
                          AND COALESCE(brs.is_posted, 0) = 0
                          AND brs.deleted_at IS NULL
                    ),
                    COALESCE(er.extra_bed_rate, 0) * COALESCE(er.extra_bed_qty, 0)
                )
                ELSE 0
            END), 0) AS EB,
            COALESCE(pb.posted_er, 0) + COALESCE(SUM(CASE
                WHEN (c.dt >= v_system_date OR COALESCE(pb.posted_rm, 0) + COALESCE(pb.posted_eb, 0) + COALESCE(pb.posted_er, 0) = 0)
                 AND ((c.dt >= er.arrival_date AND c.dt < er.departure_date)
                   OR (er.status IN (0, 1, 2) AND er.arrival_date = c.dt AND er.departure_date = c.dt AND er.is_day_use = 1))
                THEN COALESCE((
                    SELECT SUM(brs.total_amount)
                    FROM booking_room_services brs
                    WHERE brs.booking_room_id = er.id
                      AND UPPER(TRIM(brs.service_code)) IN ('ER','LO','TB','DN','GN','HN','HT','LH','MR','MS','NB','TO','WS','EI')
                      AND DATE(brs.service_date) = c.dt
                      AND COALESCE(brs.is_posted, 0) = 0
                      AND brs.deleted_at IS NULL
                ), 0)
                ELSE 0
            END), 0) AS ER,
            (
                SELECT COUNT(DISTINCT rl.room_number)
                FROM room_locks rl
                INNER JOIN rooms available_room ON available_room.room_number = rl.room_number
                WHERE COALESCE(available_room.is_internal, 0) = 0
                  AND COALESCE(available_room.room_number, '') NOT LIKE '0%'
                  AND (COALESCE(p_area, '') = '' OR available_room.area = p_area)
                  AND (
                      COALESCE(p_room_type, '') = ''
                      OR CAST(available_room.room_class_id AS CHAR) = p_room_type
                      OR EXISTS (
                          SELECT 1
                          FROM room_classes rc
                          WHERE rc.id = available_room.room_class_id
                            AND rc.code = p_room_type
                      )
                  )
                  AND UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO'
                  AND COALESCE(rl.is_active, 1) = 1
                  AND LOWER(COALESCE(rl.status, 'active')) NOT IN ('done', 'cancelled', 'unlocked')
                  AND (rl.start_date IS NULL OR DATE(rl.start_date) <= c.dt)
                  AND (rl.end_date IS NULL OR DATE(rl.end_date) >= c.dt)
            ) AS OOO
        FROM calendar c
        LEFT JOIN eligible_rooms er ON 1 = 1
        LEFT JOIN posted_bills pb ON pb.bill_date = c.dt
        GROUP BY c.dt, pb.posted_rm, pb.posted_eb, pb.posted_er
    ), daily_calc AS (
        SELECT
            report_date,
            DepRooms,
            DepAdult,
            ArrRooms,
            ArrAdult,
            OccRooms,
            OccAdult,
            HouseUse,
            FOCAll,
            GREATEST(OccRooms - HouseUse, 0) AS RoomSales,
            RM,
            EB,
            ER,
            (RM + EB + ER) AS Revenue,
            CASE
                WHEN report_date < v_system_date AND COALESCE(p_area, '') = '' THEN COALESCE(
                    CASE
                        WHEN COALESCE(p_room_type, '') = '' THEN (
                            SELECT s7003.room_available
                            FROM night_audit_room_sales_forecast_snapshots s7003
                            WHERE s7003.snapshot_date = report_date
                              AND s7003.room_available > 0
                            ORDER BY s7003.id DESC
                            LIMIT 1
                        )
                        ELSE (
                            SELECT SUM(s7005.room_available)
                            FROM night_audit_room_type_snapshots s7005
                            WHERE s7005.snapshot_date = report_date
                              AND (
                                  CAST(s7005.room_type_id AS CHAR) = p_room_type
                                  OR s7005.room_type_code = p_room_type
                              )
                            HAVING SUM(s7005.room_available) > 0
                        )
                    END,
                    GREATEST(v_total_rooms - OOO, 0)
                )
                ELSE GREATEST(v_total_rooms - OOO, 0)
            END AS RoomAvible
        FROM daily_base
    )
    SELECT
        DATE_FORMAT(report_date, '%d/%m/%Y') AS `Date`,
        DepRooms,
        DepAdult,
        ArrRooms,
        ArrAdult,
        OccRooms,
        OccAdult,
        HouseUse,
        FOCAll,
        RoomSales,
        CAST(RM AS DECIMAL(15,2)) AS RM,
        CAST(EB AS DECIMAL(15,2)) AS EB,
        CAST(ER AS DECIMAL(15,2)) AS ER,
        CAST(Revenue AS DECIMAL(15,2)) AS Revenue,
        CAST(COALESCE(ROUND((CASE WHEN v_avg_rate_inc_other = 1 THEN Revenue ELSE RM END) / NULLIF(RoomSales, 0), 0), 0) AS DECIMAL(15,2)) AS AvgRate,
        CAST(COALESCE(ROUND((CASE WHEN v_avg_rate_inc_other = 1 THEN Revenue ELSE RM END) / NULLIF(GREATEST(RoomSales - FOCAll, 0), 0), 0), 0) AS DECIMAL(15,2)) AS AvgRate2,
        RoomAvible,
        CAST(COALESCE(ROUND((OccRooms / NULLIF(RoomAvible, 0)) * 100, 2), 0) AS DECIMAL(8,2)) AS PercentOccupancy,
        CAST(COALESCE(ROUND((GREATEST(OccRooms - HouseUse - FOCAll, 0) / NULLIF(RoomAvible, 0)) * 100, 2), 0) AS DECIMAL(8,2)) AS PercentOccupancy1,
        CAST(COALESCE(ROUND(Revenue / NULLIF(RoomAvible, 0), 0), 0) AS DECIMAL(15,2)) AS RevPAR
    FROM daily_calc
    ORDER BY report_date ASC;
END
SQL;
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }

    private function syncConfiguration($db, string $database): void
    {
        $now = now();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 3, 'required' => false],
            ['name' => 'p_branch', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 4, 'required' => false],
            ['name' => 'p_room_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
            ['name' => 'p_revenue_detail', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
            ['name' => 'p_include_breakfast', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
            ['name' => 'p_area', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 8, 'required' => false],
        ];
        $fields = [
            ['name' => 'Date', 'type' => 'string'],
            ['name' => 'DepRooms', 'type' => 'integer'],
            ['name' => 'DepAdult', 'type' => 'integer'],
            ['name' => 'ArrRooms', 'type' => 'integer'],
            ['name' => 'ArrAdult', 'type' => 'integer'],
            ['name' => 'OccRooms', 'type' => 'integer'],
            ['name' => 'OccAdult', 'type' => 'integer'],
            ['name' => 'HouseUse', 'type' => 'integer'],
            ['name' => 'FOCAll', 'type' => 'integer'],
            ['name' => 'RoomSales', 'type' => 'integer'],
            ['name' => 'RM', 'type' => 'number'],
            ['name' => 'EB', 'type' => 'number'],
            ['name' => 'ER', 'type' => 'number'],
            ['name' => 'Revenue', 'type' => 'number'],
            ['name' => 'AvgRate', 'type' => 'number'],
            ['name' => 'AvgRate2', 'type' => 'number'],
            ['name' => 'RoomAvible', 'type' => 'integer'],
            ['name' => 'PercentOccupancy', 'type' => 'number'],
            ['name' => 'PercentOccupancy1', 'type' => 'number'],
            ['name' => 'RevPAR', 'type' => 'number'],
        ];
        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_user' => '',
            'p_branch' => '__current__',
            'p_room_type' => '',
            'p_revenue_detail' => 0,
            'p_include_breakfast' => 1,
            'p_area' => '',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo dự đoán bán phòng',
            'description' => 'Dự báo phòng đi, phòng đến, phòng ở, doanh thu phòng và công suất theo sp_023 legacy trên connection chi nhánh hiện tại.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => self::PROCEDURE,
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

        $definition = (require database_path('report_templates/room_forecast_reference.php'))->definition();
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        $templateValues = [
            'group' => 'Báo cáo phòng',
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
            'updated_at' => $now,
        ];
        if (! $template) {
            $templateValues['report'] = self::TEMPLATE;
            $templateValues['created_at'] = $now;
            $templateId = $db->table('templates')->insertGetId($templateValues);
        } else {
            $templateId = $template->id;
            $db->table('templates')->where('id', $templateId)->update(
                (float) ($template->version ?? 1.0) <= 1.0
                    ? $templateValues
                    : ['report_data_source_id' => $sourceId, 'updated_at' => $now]
            );
        }

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_user', 'label' => 'Người dùng / Người bán', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_branch', 'label' => 'Chi nhánh', 'control' => 'hidden', 'default' => '__current__', 'required' => false],
            ['name' => 'p_room_type', 'label' => 'Loại phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'room-classes'],
            ['name' => 'p_area', 'label' => 'Khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            ['name' => 'p_revenue_detail', 'label' => 'Hiển thị chi tiết doanh thu', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_include_breakfast', 'label' => 'Doanh thu phòng bao gồm ăn sáng', 'control' => 'checkbox', 'default' => true, 'required' => false],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => $definition['name'],
            'group' => 'Báo cáo phòng',
            'description' => 'Báo cáo dự đoán bán phòng 17 cột FO; hỗ trợ hiển thị chi tiết doanh thu (RM, EB, ER, Doanh Thu) bằng p_revenue_detail.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 169,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'housekeeping'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 169,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
