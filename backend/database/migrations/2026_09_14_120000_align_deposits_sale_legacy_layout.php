<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = (require database_path('report_templates/deposits_sale_reference.php'))->definition();

        DB::table('templates')->where('report', 'DEPOSITS_SALE_REFERENCE')->update([
            'page_size' => $definition['page_size'],
            'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'],
            'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'],
            'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'version' => '1.1',
            'updated_at' => now(),
        ]);

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_deposits_sale');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_deposits_sale(
    IN p_from_date DATE, IN p_to_date DATE, IN p_department VARCHAR(200), IN p_user VARCHAR(50),
    IN p_shift VARCHAR(5), IN p_from_time VARCHAR(5), IN p_to_time VARCHAR(5), IN p_company BIGINT,
    IN p_show_deposit TINYINT, IN p_show_amount_zero TINYINT, IN p_payment_method VARCHAR(100)
)
READS SQL DATA
BEGIN
    WITH base AS (
        SELECT p.id AS PaymentId, p.legacy_id AS LegacyPaymentId, DATE_FORMAT(p.date, '%d/%m/%Y') AS PaymentDate,
            p.open_time AS OpenTime, p.amount AS Amount, COALESCE(p.currency, 'VND') AS Currency,
            p.payment_method_id AS PaymentMethod, COALESCE(pm.name, p.payment_method_id, '') AS PaymentMethodName,
            (SELECT sb.InvoiceId FROM service_bills AS sb WHERE sb.PaymentId = p.id ORDER BY sb.Ma LIMIT 1) AS BillID,
            p.description AS Description, p.username AS Username, p.shift AS Shift, p.department_id AS DepartmentId,
            COALESCE(d.name, p.department_id, '') AS Department, COALESCE(b.id, p.booking_id) AS BookingId,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(b.id, p.booking_id, '')) AS BookingCode,
            COALESCE(br.room_number, '') AS Room,
            COALESCE(NULLIF(p.guest_display, ''), NULLIF(b.booking_name, ''), NULLIF(g.full_name, ''), '') AS GuestInfo,
            DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate, DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
            COALESCE(c.name, '') AS CompanyName,
            CASE WHEN p.amount < 0 THEN 'Hoàn Trả' WHEN COALESCE(p.pack2, '') = 'DPR' THEN 'Đặt cọc' ELSE 'Thu Ngân' END AS ShowDeposit,
            CASE WHEN p.amount < 0 THEN 0 ELSE p.amount END AS PositiveAmount, CASE WHEN p.amount < 0 THEN p.amount ELSE 0 END AS RefundAmount,
            CASE WHEN p.amount >= 0 AND COALESCE(p.pack2, '') = 'DPR' THEN p.amount ELSE 0 END AS DepositAmount,
            CASE WHEN p.amount >= 0 AND COALESCE(p.pack2, '') <> 'DPR' THEN p.amount ELSE 0 END AS CashAmount
        FROM payments AS p
        LEFT JOIN bookings AS b ON b.id = p.booking_id AND b.deleted_at IS NULL
        LEFT JOIN booking_rooms AS br ON br.id = p.booking_room_id AND br.deleted_at IS NULL
        LEFT JOIN guests AS g ON g.id = p.guest_id
        LEFT JOIN companies AS c ON c.id = COALESCE(p.company_id, b.company_id)
        LEFT JOIN payment_methods AS pm ON pm.code = p.payment_method_id
        LEFT JOIN departments AS d ON d.code = p.department_id
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE p.deleted_at IS NULL AND p.edit_flag = 0 AND p.date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_department, '') = '' OR FIND_IN_SET(p.department_id, REPLACE(p_department, ' ', '')) > 0)
          AND (COALESCE(p_user, '') = '' OR p.username = p_user) AND (COALESCE(p_shift, '') = '' OR p.shift = p_shift)
          AND (COALESCE(p_payment_method, '') = '' OR FIND_IN_SET(p.payment_method_id, REPLACE(p_payment_method, ' ', '')) > 0)
          AND (COALESCE(p_company, 0) = 0 OR COALESCE(p.company_id, b.company_id) = p_company)
          AND (COALESCE(p_show_amount_zero, 0) = 1 OR p.amount <> 0)
          AND (COALESCE(p_show_deposit, 1) = 1 OR COALESCE(p.pack2, '') <> 'DPR')
          AND (COALESCE(p_shift, '') <> '' OR COALESCE(p_from_time, '') = '' OR COALESCE(p_to_time, '') = '' OR p.open_time BETWEEN p_from_time AND p_to_time)
    )
    SELECT base.*, SUM(base.Amount) OVER (PARTITION BY base.CompanyName) AS CompanyTotal
    FROM base ORDER BY base.PaymentDate, base.OpenTime, base.CompanyName, base.BookingCode, base.PaymentId;
END
SQL);

        $fields = json_decode(DB::table('report_data_sources')->where('code', 'DEPOSITS_SALE')->value('field_schema') ?? '[]', true);
        $fields = array_values(array_filter($fields, fn (array $field) => $field['name'] !== 'BillID'));
        array_splice($fields, 8, 0, [['name' => 'BillID', 'type' => 'number', 'nullable' => true]]);
        DB::table('report_data_sources')->where('code', 'DEPOSITS_SALE')->update(['field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Preserve the compatible report layout on rollback.
    }
};
