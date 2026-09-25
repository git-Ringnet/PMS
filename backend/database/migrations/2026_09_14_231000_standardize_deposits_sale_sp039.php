<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'DEPOSITS_SALE';
    private const REPORT = 'DEPOSITS_SALE';
    private const TEMPLATE = 'DEPOSITS_SALE_REFERENCE';

    public function up(): void
    {
        // 1. Thêm / kích hoạt phòng ban MR trong database đích của migration
        foreach ([DB::getDefaultConnection()] as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $existing = DB::connection($conn)->table('departments')->where('code', 'MR')->first();
                if (! $existing) {
                    DB::connection($conn)->table('departments')->insert([
                        'code' => 'MR',
                        'name' => 'Reservation / Kinh Doanh',
                        'phone' => null,
                        'show' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::connection($conn)->table('departments')->where('code', 'MR')->update([
                        'name' => 'Reservation / Kinh Doanh',
                        'show' => 1,
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Tiếp tục xử lý cho các kết nối khả dụng
            }
        }

        // 2. Cập nhật Stored Procedure rpt_deposits_sale trên database đích
        foreach ([DB::getDefaultConnection()] as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS rpt_deposits_sale');
                DB::connection($conn)->unprepared($this->procedureSql());

                $this->syncReportConfiguration($conn);
            } catch (\Throwable $e) {
                // Tiếp tục cho các chi nhánh khác
            }
        }
    }

    private function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_deposits_sale(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(200),
    IN p_user VARCHAR(50),
    IN p_shift VARCHAR(5),
    IN p_from_time VARCHAR(5),
    IN p_to_time VARCHAR(5),
    IN p_company BIGINT,
    IN p_show_deposit TINYINT,
    IN p_show_amount_zero TINYINT,
    IN p_payment_method VARCHAR(100)
)
READS SQL DATA
BEGIN
    WITH base AS (
        SELECT
            p.id AS PaymentId,
            p.legacy_id AS LegacyPaymentId,
            DATE_FORMAT(p.date, '%d/%m/%Y') AS PaymentDate,
            p.open_time AS OpenTime,
            p.amount AS Amount,
            COALESCE(p.currency, 'VND') AS Currency,
            p.payment_method_id AS PaymentMethod,
            COALESCE(pm.name, p.payment_method_id, '') AS PaymentMethodName,
            (SELECT sb.InvoiceId FROM service_bills AS sb WHERE sb.PaymentId = p.id ORDER BY sb.Ma LIMIT 1) AS BillID,
            p.description AS Description,
            p.username AS Username,
            p.shift AS Shift,
            p.department_id AS DepartmentId,
            COALESCE(d.name, p.department_id, '') AS Department,
            COALESCE(b.id, p.booking_id) AS BookingId,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(b.id, p.booking_id, '')) AS BookingCode,
            COALESCE(br.room_number, '') AS Room,
            COALESCE(NULLIF(p.guest_display, ''), NULLIF(b.booking_name, ''), NULLIF(g.full_name, ''), '') AS GuestInfo,
            DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
            COALESCE(c.name, '') AS CompanyName,
            CASE
                WHEN p.amount < 0 THEN 'Hoàn Trả'
                WHEN COALESCE(p.pack2, '') = 'DPR' THEN 'Đặt Cọc'
                ELSE 'Thu Ngân'
            END AS ShowDeposit,
            CONCAT(
                CASE
                    WHEN p.amount < 0 THEN 'Hoàn Trả'
                    WHEN COALESCE(p.pack2, '') = 'DPR' THEN 'Đặt Cọc'
                    ELSE 'Thu Ngân'
                END,
                ' / Thanh Toán: ',
                COALESCE(p.payment_method_id, '')
            ) AS GroupHeader,
            CASE WHEN p.amount < 0 THEN 0 ELSE p.amount END AS PositiveAmount,
            CASE WHEN p.amount < 0 THEN p.amount ELSE 0 END AS RefundAmount,
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
        WHERE p.deleted_at IS NULL
          AND p.edit_flag = 0
          AND p.date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_department, '') = '' OR FIND_IN_SET(p.department_id, REPLACE(p_department, ' ', '')) > 0)
          AND (COALESCE(p_user, '') = '' OR p.username = p_user)
          AND (COALESCE(p_shift, '') = '' OR p.shift = p_shift)
          AND (COALESCE(p_payment_method, '') = '' OR FIND_IN_SET(p.payment_method_id, REPLACE(p_payment_method, ' ', '')) > 0)
          AND (COALESCE(p_company, 0) = 0 OR COALESCE(p.company_id, b.company_id) = p_company)
          AND (COALESCE(p_show_amount_zero, 0) = 1 OR p.amount <> 0)
          AND (COALESCE(p_show_deposit, 1) = 1 OR COALESCE(p.pack2, '') <> 'DPR')
          AND (COALESCE(p_shift, '') <> '' OR COALESCE(p_from_time, '') = '' OR COALESCE(p_to_time, '') = '' OR p.open_time BETWEEN p_from_time AND p_to_time)
    )
    SELECT
        base.*,
        SUM(base.Amount) OVER (PARTITION BY base.CompanyName) AS CompanyTotal
    FROM base
    ORDER BY base.PaymentDate, base.OpenTime, base.CompanyName, base.BookingCode, base.PaymentId;
END
SQL;
    }

    private function syncReportConfiguration(string $conn): void
    {
        $db = DB::connection($conn);
        $now = now();
        $database = $db->getDatabaseName();

        // Kiểm tra xem report_data_sources đã có bản ghi chưa
        $dataSource = $db->table('report_data_sources')->where('code', self::SOURCE)->first();
        if (! $dataSource) {
            return;
        }

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_department', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(200)', 'position' => 3, 'required' => false],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 4, 'required' => false],
            ['name' => 'p_shift', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(5)', 'position' => 5, 'required' => false],
            ['name' => 'p_from_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(5)', 'position' => 6, 'required' => false],
            ['name' => 'p_to_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(5)', 'position' => 7, 'required' => false],
            ['name' => 'p_company', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 8, 'required' => false],
            ['name' => 'p_show_deposit', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => true],
            ['name' => 'p_show_amount_zero', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 10, 'required' => true],
            ['name' => 'p_payment_method', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 11, 'required' => false],
        ];

        $fields = [
            'PaymentId', 'LegacyPaymentId', 'PaymentDate', 'OpenTime', 'Amount', 'Currency',
            'PaymentMethod', 'PaymentMethodName', 'BillID', 'Description', 'Username', 'Shift',
            'DepartmentId', 'Department', 'BookingId', 'BookingCode', 'Room', 'GuestInfo',
            'ArrivalDate', 'DepartureDate', 'CompanyName', 'ShowDeposit', 'GroupHeader',
            'PositiveAmount', 'RefundAmount', 'DepositAmount', 'CashAmount', 'CompanyTotal',
        ];
        $numeric = ['PaymentId', 'LegacyPaymentId', 'Amount', 'BookingId', 'BillID', 'PositiveAmount', 'RefundAmount', 'DepositAmount', 'CashAmount', 'CompanyTotal'];
        $fieldSchema = array_map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, $numeric, true) ? 'number' : 'string',
            'nullable' => ! in_array($name, ['PaymentDate', 'Amount', 'BookingCode', 'ShowDeposit', 'GroupHeader'], true),
        ], $fields);

        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_department' => 'MR',
            'p_user' => '',
            'p_shift' => '',
            'p_from_time' => '00:00',
            'p_to_time' => '23:59',
            'p_company' => null,
            'p_show_deposit' => 1,
            'p_show_amount_zero' => 0,
            'p_payment_method' => '',
        ];

        $db->table('report_data_sources')->where('code', self::SOURCE)->update([
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        $definition = (require database_path('report_templates/deposits_sale_reference.php'))->definition();

        $db->table('templates')->where('report', self::TEMPLATE)->update([
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'updated_at' => $now,
        ]);

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_shift', 'label' => 'Ca làm việc', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'report-shifts', 'options' => []],
            ['name' => 'p_from_time', 'label' => 'Từ giờ', 'control' => 'text', 'default' => '00:00', 'required' => false],
            ['name' => 'p_to_time', 'label' => 'Đến giờ', 'control' => 'text', 'default' => '23:59', 'required' => false],
            ['name' => 'p_department', 'label' => 'Chọn bộ phận', 'control' => 'select', 'default' => 'MR', 'required' => false, 'options_source' => 'service-departments', 'options' => []],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies', 'options' => []],
            ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users', 'options' => []],
            ['name' => 'p_payment_method', 'label' => 'Phương thức thanh toán', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [
                ['label' => 'Tất cả', 'value' => ''],
                ['label' => 'Tiền mặt', 'value' => 'CA'],
                ['label' => 'Công nợ', 'value' => 'AC'],
                ['label' => 'Chuyển khoản', 'value' => 'BT'],
                ['label' => 'Thẻ', 'value' => 'CD'],
                ['label' => 'Voucher', 'value' => 'VO'],
            ]],
            ['name' => 'p_show_deposit', 'label' => 'Hiển thị tiền cọc', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_show_amount_zero', 'label' => 'Hiển thị tiền = 0', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];

        $db->table('report_definitions')->where('code', self::REPORT)->update([
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Preserve compatible stored procedure and layout.
    }
};
