<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEMPLATE = 'DEPOSITS_SALE_REFERENCE';

    public function up(): void
    {
        $systemConnection = config('database_domains.system_connection', 'mysql_system');
        $candidates = array_merge(
            [Schema::getConnection()->getName(), DB::getDefaultConnection()],
            array_values(config('database_domains.branch_connections', []))
        );
        $connections = array_values(array_unique(array_filter(
            $candidates,
            static fn (string $connection): bool => $connection !== $systemConnection
                && config("database.connections.{$connection}") !== null
        )));
        $visitedDatabases = [];

        foreach ($connections as $connection) {
            $db = DB::connection($connection);
            if ($db->getDriverName() !== 'mysql' || ! $db->getSchemaBuilder()->hasTable('payments')) {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_deposits_sale');
            $db->unprepared($this->procedureSql());

            $this->patchSavedTemplate($db, $connection);
        }
    }

    public function down(): void
    {
        // Keep the corrected subtotal and actor source when rolling back unrelated changes.
    }

    private function patchSavedTemplate($db, string $connection): void
    {
        if (! $db->getSchemaBuilder()->hasTable('templates')) {
            return;
        }

        $templates = $db->table('templates')
            ->where('report', self::TEMPLATE)
            ->get(['id', 'content_json', 'content_html']);

        foreach ($templates as $template) {
            $content = is_array($template->content_json)
                ? $template->content_json
                : json_decode((string) $template->content_json, true);
            if (! is_array($content) || ! is_array($content['detail'] ?? null)) {
                Log::warning('Skipped DEPOSITS_SALE subtotal update because the saved Design structure is invalid.', [
                    'connection' => $connection,
                    'template_id' => $template->id,
                ]);
                continue;
            }

            $cellFound = false;
            foreach ($content['detail'] as &$block) {
                if (($block['id'] ?? '') !== 'deposits_sale_table'
                    || ! is_array($block['customRows'] ?? null)) {
                    continue;
                }

                foreach ($block['customRows'] as &$customRow) {
                    if (($customRow['id'] ?? '') !== 'deposits_sale_company_total'
                        || ! is_array($customRow['cells'] ?? null)) {
                        continue;
                    }

                    foreach ($customRow['cells'] as &$cell) {
                        if (($cell['id'] ?? '') !== 'company_total_value') {
                            continue;
                        }

                        $cell['binding'] = 'group.sum.Amount';
                        $cellFound = true;
                    }
                    unset($cell);
                }
                unset($customRow);
            }
            unset($block);

            $currentHtml = (string) ($template->content_html ?? '');
            $contentHtml = str_replace(
                ['{{row.CompanyTotal|number}}', '{{row.CompanyTotal}}'],
                ['{{group.sum.Amount|number}}', '{{group.sum.Amount}}'],
                $currentHtml
            );
            if (! $cellFound || $contentHtml === $currentHtml) {
                Log::warning('Skipped DEPOSITS_SALE subtotal update because the saved Design cell or HTML binding was not found.', [
                    'connection' => $connection,
                    'template_id' => $template->id,
                ]);
                continue;
            }

            $db->table('templates')->where('id', $template->id)->update([
                'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content_html' => $contentHtml,
                'updated_at' => now(),
            ]);
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
            COALESCE(NULLIF(p.created_by, ''), NULLIF(p.username, ''), '') AS Username,
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
          AND UPPER(TRIM(COALESCE(p.payment_method_id, ''))) <> 'AC'
          AND p.date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_department, '') = '' OR FIND_IN_SET(p.department_id, REPLACE(p_department, ' ', '')) > 0)
          AND (COALESCE(p_user, '') = '' OR COALESCE(NULLIF(p.created_by, ''), NULLIF(p.username, ''), '') = p_user)
          AND (COALESCE(p_shift, '') = '' OR p.shift = p_shift)
          AND (COALESCE(p_payment_method, '') = '' OR FIND_IN_SET(p.payment_method_id, REPLACE(p_payment_method, ' ', '')) > 0)
          AND (COALESCE(p_company, 0) = 0 OR COALESCE(p.company_id, b.company_id) = p_company)
          AND (COALESCE(p_show_amount_zero, 0) = 1 OR p.amount <> 0)
          AND (COALESCE(p_show_deposit, 1) = 1 OR COALESCE(p.pack2, '') <> 'DPR')
          AND (COALESCE(p_shift, '') <> '' OR COALESCE(p_from_time, '') = '' OR COALESCE(p_to_time, '') = '' OR p.open_time BETWEEN p_from_time AND p_to_time)
    )
    SELECT
        base.*,
        SUM(base.Amount) OVER (PARTITION BY base.GroupHeader, base.CompanyName) AS CompanyTotal
    FROM base
    ORDER BY base.PaymentDate, base.OpenTime, base.CompanyName, base.BookingCode, base.PaymentId;
END
SQL;
    }
};
