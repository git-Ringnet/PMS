<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'PAID_COMPANY_DEBTS';
    private const REPORT = 'PAID_COMPANY_DEBTS';
    private const TEMPLATE = 'PAID_COMPANY_DEBTS_REFERENCE';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_paid_company_debts');
        DB::unprepared($this->procedureSql());

        $this->seedReportConfiguration();
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_paid_company_debts(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_view_type TINYINT,
    IN p_company_id BIGINT,
    IN p_group_by_company TINYINT
)
READS SQL DATA
BEGIN
    WITH RawPayments AS (
        SELECT
            p.id AS DebtRowKey,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(b.id, '')) AS BookingId,
            COALESCE(r.room_number, '') AS Room,
            COALESCE(b.booking_name, p.guest_display, '') AS BookingName,
            DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDate,
            p.amount AS AmountCN,
            COALESCE(p.payment_method_id, 'AC') AS PaymentMethodCN,
            DATE_FORMAT(p.date, '%d/%m/%Y') AS DateCN,
            p.date AS RawDateCN,
            COALESCE(p.created_by, p.username, '') AS UserCN,
            DATE_FORMAT(s.payment_date, '%d/%m/%Y') AS DateTT,
            s.payment_date AS RawDateTT,
            s.amount AS AmountTT,
            COALESCE(s.payment_method_id, '') AS PaymentMethodTT,
            COALESCE(s.created_by, '') AS UserTT,
            COALESCE(s.description, '') AS Description,
            COALESCE(c.name, 'Chưa gán công ty') AS CompanyName,
            c.id AS CompanyId,
            s.id AS SettlementId,
            CASE
                WHEN COALESCE(p_view_type, 0) = 1 THEN DATE_FORMAT(p.date, '%d/%m/%Y')
                ELSE DATE_FORMAT(s.payment_date, '%d/%m/%Y')
            END AS DateGroup,
            CASE
                WHEN COALESCE(p_view_type, 0) = 1 THEN p.date
                ELSE s.payment_date
            END AS RawDateGroup
        FROM payments AS p
        INNER JOIN payment_debt_settlements AS s
            ON (s.payment_id = p.id OR s.legacy_payment_table_id = p.legacy_id)
           AND s.edit_flag = 0
        LEFT JOIN bookings AS b
            ON b.id = p.booking_id
           AND b.deleted_at IS NULL
        LEFT JOIN booking_rooms AS br
            ON br.id = p.booking_room_id
           AND br.deleted_at IS NULL
        LEFT JOIN rooms AS r
            ON r.room_number = br.room_number
        LEFT JOIN companies AS c
            ON c.id = p.company_id
        LEFT JOIN hotel_settings AS hs
            ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE p.payment_method_id = 'AC'
          AND p.amount > 0
          AND p.edit_flag = 0
          AND (p_company_id IS NULL OR p_company_id = 0 OR p.company_id = p_company_id)
          AND (
              (COALESCE(p_view_type, 0) = 0 AND s.payment_date BETWEEN p_from_date AND p_to_date)
              OR (p_view_type = 1 AND p.date BETWEEN p_from_date AND p_to_date)
          )
    ),
    WindowedPayments AS (
        SELECT
            rp.*,
            ROW_NUMBER() OVER (PARTITION BY rp.DebtRowKey ORDER BY rp.RawDateTT, rp.SettlementId) AS PaymentSortNo,
            SUM(rp.AmountTT) OVER (
                PARTITION BY rp.DebtRowKey
                ORDER BY rp.RawDateTT, rp.SettlementId
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) AS PaidRunning,
            SUM(rp.AmountTT) OVER (PARTITION BY rp.DebtRowKey) AS PaidTotal
        FROM RawPayments AS rp
    ),
    FinalPayments AS (
        SELECT
            wp.*,
            CASE WHEN wp.PaymentSortNo = 1 THEN wp.AmountCN ELSE 0 END AS AmountCNForTotal,
            GREATEST(0, wp.AmountCN - wp.PaidRunning) AS RemainAmount,
            CASE WHEN wp.PaidTotal >= wp.AmountCN THEN '☑' ELSE '' END AS IsPaidMarker
        FROM WindowedPayments AS wp
    )
    SELECT
        fp.DebtRowKey,
        fp.BookingId,
        fp.Room,
        fp.BookingName,
        fp.ArrivalDate,
        fp.DepartureDate,
        fp.AmountCN,
        fp.AmountCNForTotal,
        fp.PaymentMethodCN,
        fp.DateCN,
        fp.UserCN,
        fp.IsPaidMarker,
        fp.DateTT,
        fp.AmountTT,
        fp.PaymentMethodTT,
        fp.RemainAmount,
        fp.UserTT,
        fp.Description,
        fp.CompanyName AS Company,
        fp.CompanyName,
        fp.DateGroup,
        SUM(fp.AmountCNForTotal) OVER (PARTITION BY fp.DateGroup, fp.CompanyId) AS CompanyTotalDebt,
        SUM(fp.AmountTT) OVER (PARTITION BY fp.DateGroup, fp.CompanyId) AS CompanyTotalPaid,
        GREATEST(0, SUM(fp.AmountCNForTotal) OVER (PARTITION BY fp.DateGroup, fp.CompanyId) - SUM(fp.AmountTT) OVER (PARTITION BY fp.DateGroup, fp.CompanyId)) AS CompanyRemaining,
        SUM(fp.AmountCNForTotal) OVER (PARTITION BY fp.DateGroup) AS DateTotalDebt,
        SUM(fp.AmountTT) OVER (PARTITION BY fp.DateGroup) AS DateTotalPaid,
        GREATEST(0, SUM(fp.AmountCNForTotal) OVER (PARTITION BY fp.DateGroup) - SUM(fp.AmountTT) OVER (PARTITION BY fp.DateGroup)) AS DateRemaining
    FROM FinalPayments AS fp
    ORDER BY
        CASE WHEN COALESCE(p_group_by_company, 0) = 1 THEN fp.CompanyName ELSE fp.RawDateGroup END ASC,
        CASE WHEN COALESCE(p_group_by_company, 0) = 1 THEN fp.RawDateGroup ELSE fp.CompanyName END ASC,
        fp.DebtRowKey ASC,
        fp.PaymentSortNo ASC;
END
SQL;
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_paid_company_debts');
    }

    private function seedReportConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 2, 'required' => true],
            ['name' => 'p_view_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
            ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 4, 'required' => false],
            ['name' => 'p_group_by_company', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 5, 'required' => false],
        ];

        $fields = collect([
            'DebtRowKey', 'BookingId', 'Room', 'BookingName', 'ArrivalDate', 'DepartureDate',
            'AmountCN', 'AmountCNForTotal', 'PaymentMethodCN', 'DateCN', 'UserCN', 'IsPaidMarker',
            'DateTT', 'AmountTT', 'PaymentMethodTT', 'RemainAmount', 'UserTT', 'Description',
            'Company', 'CompanyName', 'DateGroup', 'CompanyTotalDebt', 'CompanyTotalPaid',
            'CompanyRemaining', 'DateTotalDebt', 'DateTotalPaid', 'DateRemaining',
        ])->map(function (string $name): array {
            $numeric = [
                'DebtRowKey', 'AmountCN', 'AmountCNForTotal', 'AmountTT', 'RemainAmount',
                'CompanyTotalDebt', 'CompanyTotalPaid', 'CompanyRemaining', 'DateTotalDebt',
                'DateTotalPaid', 'DateRemaining',
            ];
            return [
                'name' => $name,
                'type' => in_array($name, $numeric, true) ? 'number' : 'string',
                'nullable' => true,
            ];
        })->all();

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'p_view_type' => 0,
            'p_company_id' => null,
            'p_group_by_company' => 0,
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo công nợ đã thanh toán',
            'description' => 'MySQL chuyển đổi theo legacy sp_294; danh sách các khoản nợ công ty đã thanh toán/giải trừ.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_paid_company_debts',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/paid_company_debts_reference.php'))->definition();
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo công nợ',
            'name' => 'Báo cáo công nợ đã thanh toán',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults),
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
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_view_type',
                'label' => 'Xem theo',
                'control' => 'select',
                'default' => 0,
                'required' => true,
                'options' => [
                    ['value' => 0, 'label' => 'Xem theo ngày trả công nợ'],
                    ['value' => 1, 'label' => 'Xem theo ngày công nợ'],
                ],
            ],
            [
                'name' => 'p_company_id',
                'label' => 'Chọn công ty',
                'control' => 'select',
                'default' => '',
                'required' => false,
                'options' => [],
                'options_source' => 'companies',
            ],
            [
                'name' => 'p_group_by_company',
                'label' => 'Nhóm theo công ty',
                'control' => 'checkbox',
                'default' => false,
                'required' => false,
            ],
        ];

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo công nợ đã thanh toán',
            'group' => 'Báo cáo công nợ',
            'description' => 'Báo cáo chi tiết các khoản nợ công ty đã thanh toán, đối trừ và số dư còn lại.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 39,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 39,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
