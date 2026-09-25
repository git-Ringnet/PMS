<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CONNECTIONS = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
    private const SOURCE = 'RPT_TOTAL_REVENUE';
    private const REPORT = 'TOTAL_REVENUE';
    private const TEMPLATE = 'TOTAL_REVENUE_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach (self::CONNECTIONS as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $db->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_total_revenue`');
                $db->unprepared($this->procedureSql());
                $this->syncReportConfiguration($connectionName);
            } catch (Throwable $exception) {
                Log::warning('Total revenue report migration skipped unavailable branch connection.', [
                    'connection' => $connectionName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE `rpt_total_revenue`(
    IN p_date DATE,
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT
)
READS SQL DATA
BEGIN
    DECLARE v_report_date DATE;
    SET v_report_date = COALESCE(p_date, CURRENT_DATE());

    WITH raw_charges AS (
        SELECT
            COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0)) AS booking_id,
            DATE(COALESCE(rnb.date, sb.Date)) AS charge_date,
            UPPER(TRIM(COALESCE(sb.ServiceId, ''))) AS service_id,
            UPPER(TRIM(COALESCE(sb.DepartmentId, ''))) AS department_id,
            COALESCE(sb.Amount, 0) AS amount
        FROM service_bills AS sb
        LEFT JOIN booking_rooms AS br
            ON br.id = COALESCE(sb.RentalRoomId2, sb.RentalRoomId1)
        LEFT JOIN room_night_bills AS rnb
            ON rnb.bill_id = sb.Ma
        WHERE COALESCE(sb.Edit, 0) = 0
          AND COALESCE(sb.Status, 1) <> 3
          AND DATE(COALESCE(rnb.date, sb.Date)) <= v_report_date
          AND COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0)) IS NOT NULL
    ),
    eligible_bookings AS (
        SELECT
            b.id AS booking_id,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS booking_code,
            COALESCE(NULLIF(b.booking_name, ''), 'KHÁCH LẺ') AS booking_name,
            COALESCE(NULLIF(c.name, ''), NULLIF(c.trading_name, ''), NULLIF(c.code, ''), 'KHÁCH LẺ') AS company_name,
            b.arrival_date,
            b.departure_date,
            COUNT(DISTINCT br.id) AS room_count,
            GROUP_CONCAT(DISTINCT NULLIF(br.room_number, '') ORDER BY br.room_number SEPARATOR ', ') AS room_numbers,
            CASE
                WHEN b.status = 2 THEN 1
                WHEN COUNT(DISTINCT br.id) > 0
                     AND COUNT(DISTINCT br.id) = SUM(CASE WHEN br.status = 2 THEN 1 ELSE 0 END)
                    THEN 1
                ELSE 0
            END AS is_checked_out
        FROM bookings AS b
        LEFT JOIN booking_rooms AS br
            ON br.booking_id = b.id
           AND br.deleted_at IS NULL
        LEFT JOIN companies AS c
            ON c.id = b.company_id
        LEFT JOIN hotel_settings AS hs
            ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND (COALESCE(p_company_id, 0) = 0 OR b.company_id = p_company_id)
          AND (COALESCE(p_booking_id, 0) = 0 OR b.id = p_booking_id)
        GROUP BY
            b.id, b.booking_name, c.name, c.trading_name, c.code,
            b.arrival_date, b.departure_date, b.status, hs.prefix_booking_id
        HAVING COUNT(DISTINCT br.id) > 0
    ),
    scoped_bookings AS (
        SELECT eb.*
        FROM eligible_bookings AS eb
        WHERE v_report_date BETWEEN eb.arrival_date AND eb.departure_date
           OR EXISTS (
                SELECT 1
                FROM raw_charges AS rc
                WHERE rc.booking_id = eb.booking_id
                  AND rc.charge_date = v_report_date
            )
    ),
    revenue_totals AS (
        SELECT
            rc.booking_id,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND rc.service_id = 'RM' THEN rc.amount ELSE 0 END), 0) AS room_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND rc.service_id IN ('EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF') THEN rc.amount ELSE 0 END), 0) AS extra_room_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND rc.service_id = 'MB' THEN rc.amount ELSE 0 END), 0) AS minibar_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND rc.service_id = 'LA' THEN rc.amount ELSE 0 END), 0) AS laundry_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND rc.service_id = 'BR' THEN rc.amount ELSE 0 END), 0) AS damage_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date AND (rc.service_id = 'FB' OR rc.department_id = 'FB') THEN rc.amount ELSE 0 END), 0) AS fb_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date = v_report_date
                               AND rc.service_id NOT IN ('RM', 'EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF', 'MB', 'LA', 'BR', 'FB')
                               AND rc.department_id <> 'FB'
                              THEN rc.amount ELSE 0 END), 0) AS other_service_revenue,
            COALESCE(SUM(CASE WHEN rc.charge_date < v_report_date THEN rc.amount ELSE 0 END), 0) AS previous_days_revenue
        FROM raw_charges AS rc
        GROUP BY rc.booking_id
    ),
    payment_totals AS (
        SELECT
            COALESCE(p.booking_id, br.booking_id) AS booking_id,
            /* Deposit rows (p.pack2 = DPR / p.pack4 = AP) stay in the same signed
               allocation stream; edit_flag/reversal rows are intentionally
               handled without dropping negative amounts. */
            COALESCE(SUM(CASE
                WHEN COALESCE(pm.payment_group, 0) = 1
                 AND UPPER(TRIM(COALESCE(pm.code, p.payment_method_id, ''))) <> 'HH'
                 AND LOWER(COALESCE(pm.name, '')) NOT LIKE '%commission%'
                    THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS paid_cash,
            COALESCE(SUM(CASE
                WHEN COALESCE(pm.payment_group, 0) = 2
                 AND UPPER(TRIM(COALESCE(pm.code, p.payment_method_id, ''))) <> 'HH'
                 AND LOWER(COALESCE(pm.name, '')) NOT LIKE '%commission%'
                    THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS paid_bank,
            COALESCE(SUM(CASE
                WHEN COALESCE(pm.payment_group, 0) = 3
                  OR UPPER(TRIM(COALESCE(pm.code, p.payment_method_id, ''))) = 'HH'
                  OR LOWER(COALESCE(pm.name, '')) LIKE '%commission%'
                    THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS paid_commission,
            COALESCE(SUM(CASE
                WHEN COALESCE(pm.payment_group, 0) = 4
                 AND UPPER(TRIM(COALESCE(pm.code, p.payment_method_id, ''))) <> 'HH'
                 AND LOWER(COALESCE(pm.name, '')) NOT LIKE '%commission%'
                    THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS payment_debt
        FROM payments AS p
        LEFT JOIN booking_rooms AS br
            ON br.id = p.booking_room_id
        LEFT JOIN payment_methods AS pm
            ON pm.code = p.payment_method_id
        WHERE p.date <= v_report_date
          AND COALESCE(p.status, 1) <> 3
          AND COALESCE(p.edit_flag, 0) = 0
          AND p.deleted_at IS NULL
        GROUP BY COALESCE(p.booking_id, br.booking_id)
    ),
    revenue_calculated AS (
        SELECT
            sb.*,
            COALESCE(rt.room_revenue, 0) AS room_revenue,
            COALESCE(rt.extra_room_revenue, 0) AS extra_room_revenue,
            COALESCE(rt.minibar_revenue, 0) AS minibar_revenue,
            COALESCE(rt.laundry_revenue, 0) AS laundry_revenue,
            COALESCE(rt.damage_revenue, 0) AS damage_revenue,
            COALESCE(rt.fb_revenue, 0) AS fb_revenue,
            COALESCE(rt.other_service_revenue, 0) AS other_service_revenue,
            COALESCE(rt.room_revenue, 0)
                + COALESCE(rt.extra_room_revenue, 0)
                + COALESCE(rt.minibar_revenue, 0)
                + COALESCE(rt.laundry_revenue, 0)
                + COALESCE(rt.damage_revenue, 0)
                + COALESCE(rt.fb_revenue, 0)
                + COALESCE(rt.other_service_revenue, 0) AS daily_total_revenue,
            COALESCE(rt.previous_days_revenue, 0) AS previous_days_revenue,
            COALESCE(rt.room_revenue, 0)
                + COALESCE(rt.extra_room_revenue, 0)
                + COALESCE(rt.minibar_revenue, 0)
                + COALESCE(rt.laundry_revenue, 0)
                + COALESCE(rt.damage_revenue, 0)
                + COALESCE(rt.fb_revenue, 0)
                + COALESCE(rt.other_service_revenue, 0)
                + COALESCE(rt.previous_days_revenue, 0) AS grand_total_revenue,
            COALESCE(pt.paid_cash, 0) AS paid_cash,
            COALESCE(pt.paid_bank, 0) AS paid_bank,
            COALESCE(pt.paid_commission, 0) AS paid_commission,
            COALESCE(pt.payment_debt, 0) AS payment_debt
        FROM scoped_bookings AS sb
        LEFT JOIN revenue_totals AS rt ON rt.booking_id = sb.booking_id
        LEFT JOIN payment_totals AS pt ON pt.booking_id = sb.booking_id
    ),
    allocations AS (
        SELECT
            rc.*,
            CASE
                WHEN rc.is_checked_out = 1
                    THEN rc.grand_total_revenue - rc.paid_cash - rc.paid_bank - rc.paid_commission
                ELSE 0
            END AS paid_debt,
            CASE
                WHEN rc.is_checked_out = 1 THEN 0
                ELSE rc.grand_total_revenue - rc.paid_cash - rc.paid_bank - rc.paid_commission - rc.payment_debt
            END AS inhouse_balance
        FROM revenue_calculated AS rc
    )
    SELECT
        ROW_NUMBER() OVER (ORDER BY a.arrival_date, a.booking_id) AS stt,
        a.booking_code,
        CONCAT(a.booking_name, CASE WHEN COALESCE(a.room_numbers, '') = '' THEN '' ELSE CONCAT(' - ', a.room_numbers) END) AS guest_name_rooms,
        a.company_name,
        DATE_FORMAT(a.arrival_date, '%d/%m/%Y') AS arrival_date_display,
        DATE_FORMAT(a.departure_date, '%d/%m/%Y') AS departure_date_display,
        a.room_count,
        a.room_revenue,
        a.extra_room_revenue,
        a.minibar_revenue,
        a.laundry_revenue,
        a.damage_revenue,
        a.fb_revenue,
        a.other_service_revenue,
        a.daily_total_revenue,
        a.previous_days_revenue,
        a.grand_total_revenue,
        a.paid_cash,
        a.paid_bank,
        a.paid_commission,
        a.paid_debt,
        a.inhouse_balance
    FROM allocations AS a
    WHERE a.grand_total_revenue <> 0
    ORDER BY a.arrival_date, a.booking_id;
END
SQL;
    }

    private function syncReportConfiguration(string $connectionName): void
    {
        $db = DB::connection($connectionName);
        $database = $db->getDatabaseName();
        $now = now();

        $parameters = [
            ['name' => 'p_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 2, 'required' => false],
            ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 3, 'required' => false],
        ];
        $fields = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'type' => in_array($name, ['stt', 'room_count'], true) ? 'integer' : (in_array($name, [
                    'room_revenue', 'extra_room_revenue', 'minibar_revenue', 'laundry_revenue', 'damage_revenue',
                    'fb_revenue', 'other_service_revenue', 'daily_total_revenue', 'previous_days_revenue',
                    'grand_total_revenue', 'paid_cash', 'paid_bank', 'paid_commission', 'paid_debt', 'inhouse_balance',
                ], true) ? 'decimal' : 'string'),
            ],
            [
                'stt', 'booking_code', 'guest_name_rooms', 'company_name', 'arrival_date_display', 'departure_date_display',
                'room_count', 'room_revenue', 'extra_room_revenue', 'minibar_revenue', 'laundry_revenue', 'damage_revenue',
                'fb_revenue', 'other_service_revenue', 'daily_total_revenue', 'previous_days_revenue', 'grand_total_revenue',
                'paid_cash', 'paid_bank', 'paid_commission', 'paid_debt', 'inhouse_balance',
            ]
        );
        $defaults = ['p_date' => now()->toDateString(), 'p_company_id' => 0, 'p_booking_id' => 0];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo tổng doanh thu',
            'description' => 'Bảng kê doanh thu theo booking theo Sheet 72/sp_292; phase 1 dùng schema runtime hiện tại.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_total_revenue',
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

        $definition = (require database_path('report_templates/total_revenue_reference.php'))->definition();
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
            'version' => $definition['version'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_date', 'label' => 'Ngày', 'control' => 'date', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '0', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_booking_id', 'label' => 'Đăng ký', 'control' => 'select', 'default' => '0', 'required' => false, 'options_source' => 'bookings'],
        ];
        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => $definition['name'],
            'group' => 'Báo cáo thống kê lễ tân',
            'description' => 'Bảng kê chi tiết doanh thu theo booking, thanh toán và số dư phòng còn ở theo Sheet 72/sp_292.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 41,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 41,
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
        // Keep the report configuration recoverable; no destructive rollback is required.
    }
};
