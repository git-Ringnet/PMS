<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NORMAL_REPORTS = [
        'LAUNDRY_INVOICES' => ['procedure' => 'rpt_laundry_invoices', 'outlet' => 'LA'],
        'BREAKAGE_INVOICES' => ['procedure' => 'rpt_breakage_invoices', 'outlet' => 'BR'],
        'MINIBAR_INVOICES' => ['procedure' => 'rpt_minibar_invoices', 'outlet' => 'MB'],
    ];

    private const PRODUCT_REPORTS = [
        'LAUNDRY_INVOICES_BY_PRODUCT' => ['procedure' => 'rpt_laundry_invoices_by_product', 'outlet' => 'LA'],
        'BREAKAGE_INVOICES_BY_PRODUCT' => ['procedure' => 'rpt_breakage_invoices_by_product', 'outlet' => 'BR'],
        'MINIBAR_INVOICES_BY_PRODUCT' => ['procedure' => 'rpt_minibar_invoices_by_product', 'outlet' => 'MB'],
    ];

    private const FREE_REPORTS = [
        'LAUNDRY_FREE_INVOICES' => ['procedure' => 'rpt_laundry_free_invoices', 'outlet' => 'LA'],
        'BREAKAGE_FREE_INVOICES' => ['procedure' => 'rpt_breakage_free_invoices', 'outlet' => 'BR'],
        'MINIBAR_FREE_INVOICES' => ['procedure' => 'rpt_minibar_free_invoices', 'outlet' => 'MB'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::NORMAL_REPORTS as $code => $definition) {
            $this->replaceProcedure($definition['procedure'], $this->normalProcedureSql($definition['procedure'], $definition['outlet']));
            $this->updateFields($code, $this->normalFields());
        }

        foreach (self::PRODUCT_REPORTS as $code => $definition) {
            $this->replaceProcedure($definition['procedure'], $this->productProcedureSql($definition['procedure'], $definition['outlet']));
            $this->updateFields($code, $definition['outlet'] === 'MB' ? $this->minibarProductFields() : $this->productFields());
            $this->applyProductUiContract($code);
        }

        foreach (self::FREE_REPORTS as $code => $definition) {
            $this->replaceProcedure($definition['procedure'], $this->freeProcedureSql($definition['procedure'], $definition['outlet']));
            $this->updateFields($code, $this->freeFields());
            $this->applyFreeUiContract($code);
        }

        foreach ([
            'LAUNDRY_INVOICES_STANDARD' => 'laundry_invoices_reference.php',
            'BREAKAGE_INVOICES_STANDARD' => 'breakage_invoices_reference.php',
            'MINIBAR_INVOICES_STANDARD' => 'minibar_invoices_reference.php',
            'LAUNDRY_INVOICES_BY_PRODUCT_STANDARD' => 'laundry_invoices_by_product_reference.php',
            'BREAKAGE_INVOICES_BY_PRODUCT_STANDARD' => 'breakage_invoices_by_product_reference.php',
            'MINIBAR_INVOICES_BY_PRODUCT_STANDARD' => 'minibar_invoices_by_product_reference.php',
            'LAUNDRY_FREE_INVOICES_STANDARD' => 'laundry_free_invoices_reference.php',
            'BREAKAGE_FREE_INVOICES_STANDARD' => 'breakage_free_invoices_reference.php',
            'MINIBAR_FREE_INVOICES_STANDARD' => 'minibar_free_invoices_reference.php',
        ] as $template => $provider) {
            $this->syncTemplate($template, $provider);
        }
    }

    private function replaceProcedure(string $name, string $sql): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS `'.$name.'`');
        DB::unprepared($sql);
    }

    private function normalProcedureSql(string $procedure, string $outlet): string
    {
        $sql = <<<'SQL'
CREATE PROCEDURE `__PROCEDURE__`(
    IN p_from_date DATE, IN p_to_date DATE, IN p_shift VARCHAR(20),
    IN p_department VARCHAR(50), IN p_user VARCHAR(50), IN p_view_type VARCHAR(20),
    IN p_order_by VARCHAR(30), IN p_order_type VARCHAR(4), IN p_show_details TINYINT
)
READS SQL DATA
BEGIN
    WITH product_lines AS (
        SELECT d.BillId, TRIM(d.Product) AS Product, SUM(d.Quantity) AS Quantity, SUM(d.TotalAmount) AS TotalAmount
        FROM housekeeping_service_bill_details d
        WHERE d.Deleted = 0
        GROUP BY d.BillId, TRIM(d.Product)
    ), product_rollup AS (
        SELECT BillId,
            GROUP_CONCAT(CONCAT('* ', Product, ' - ', CAST(Quantity AS CHAR)) ORDER BY Product SEPARATOR '') AS Product,
            CONCAT('[', GROUP_CONCAT(JSON_OBJECT('Product', Product, 'Quantity', Quantity, 'TotalAmount', TotalAmount) ORDER BY Product SEPARATOR ','), ']') AS ProductItems
        FROM product_lines
        WHERE NULLIF(Product, '') IS NOT NULL
        GROUP BY BillId
    ), payment_ranked AS (
        SELECT p.payment_id, p.payment_method_id AS PaymentMethod,
            ROW_NUMBER() OVER (PARTITION BY p.payment_id ORDER BY COALESCE(p.legacy_id, p.id), p.id) AS PaymentRank
        FROM payments p
        WHERE p.payment_id IS NOT NULL
    ), payment_rollup AS (
        SELECT payment_id, PaymentMethod FROM payment_ranked WHERE PaymentRank = 1
    ), report_rows AS (
        SELECT
            ROW_NUMBER() OVER (ORDER BY
                CASE WHEN UPPER(COALESCE(p_order_by, 'MA')) = 'ROOM' AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC' THEN h.RoomNo END ASC,
                CASE WHEN UPPER(COALESCE(p_order_by, 'MA')) = 'ROOM' AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC' THEN h.RoomNo END DESC,
                CASE WHEN UPPER(COALESCE(p_order_by, 'MA')) = 'DATE' AND UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC' THEN sb.Date END ASC,
                CASE WHEN UPPER(COALESCE(p_order_by, 'MA')) = 'DATE' AND UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC' THEN sb.Date END DESC,
                CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC' THEN sb.Ma END ASC,
                CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC' THEN sb.Ma END DESC
            ) AS STT,
            COALESCE(h.BookingId, sb.RegisterID2, sb.RegisterId1) AS BookingId,
            CASE
                WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN COALESCE(h.NumOfRoom, h.RoomNo)
                WHEN sb.RegisterID2 IS NULL AND sb.RentalRoomId2 IS NOT NULL THEN h.RoomNo
                ELSE ''
            END AS Room,
            COALESCE(NULLIF(sb.Guest, ''), NULLIF(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), ''), NULLIF(h.GuestId, '')) AS Guest,
            sb.DescriptionServive, COALESCE(pr.Product, '') AS Product,
            h.BillOriginalAmount AS TotalAmount, h.BillDiscountAmount AS DiscountAmount,
            COALESCE(h.BillTotalAmount, h.BillAmount) AS NetAmount, sb.PaymentId AS PaymentID,
            h.BillNote, COALESCE(sb.Username, h.BillUsername) AS Username, COALESCE(sb.Ca, CAST(h.BillShift AS CHAR)) AS Ca,
            COALESCE(pay.PaymentMethod, '') AS HTTT,
            DATE_FORMAT(CASE WHEN UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'FREE' THEN h.Date ELSE sb.Date END, '%d/%m/%Y') AS DateGroup,
            CASE WHEN pay.PaymentMethod = 'CL' THEN 'Free' WHEN sb.Edit = 1 THEN 'Correct' ELSE 'Post' END AS Status,
            COALESCE(pr.ProductItems, JSON_ARRAY()) AS ProductItems,
            SUM(h.BillOriginalAmount) OVER () AS ReportTotalAmount,
            SUM(h.BillDiscountAmount) OVER () AS ReportDiscountAmount,
            SUM(COALESCE(h.BillTotalAmount, h.BillAmount)) OVER () AS ReportNetAmount
        FROM housekeeping_service_bills h
        JOIN service_bills sb ON sb.Ma = h.BillServiceId
        LEFT JOIN product_rollup pr ON pr.BillId = h.Ma
        LEFT JOIN payment_rollup pay ON pay.payment_id = sb.PaymentId
        LEFT JOIN guests g ON g.id = h.GuestId
        WHERE sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND sb.Outlet LIKE CONCAT('%', '__OUTLET__', '%')
          AND sb.Status <> 4
          AND (p_shift IS NULL OR p_shift = '' OR sb.Ca LIKE CONCAT('%', p_shift, '%'))
          AND (p_department IS NULL OR p_department = '' OR sb.DepartmentId LIKE CONCAT('%', p_department, '%'))
          AND (p_user IS NULL OR p_user = '' OR sb.Username LIKE CONCAT('%', p_user, '%'))
          AND (
              UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'ALL'
              OR (UPPER(p_view_type) = 'POST' AND sb.Edit = 0 AND COALESCE(pay.PaymentMethod, '') <> 'CL')
              OR (UPPER(p_view_type) = 'CORRECT' AND sb.Edit = 1 AND h.BillEdit = 1)
              OR (UPPER(p_view_type) = 'FREE' AND pay.PaymentMethod = 'CL')
          )
    )
    SELECT STT, BookingId, Room, Guest, DescriptionServive, Product, TotalAmount, DiscountAmount, NetAmount,
           PaymentID, BillNote, Username, Ca, HTTT, DateGroup, Status, ProductItems,
           ReportTotalAmount, ReportDiscountAmount, ReportNetAmount
    FROM report_rows
    ORDER BY STT;
END
SQL;

        return str_replace(['__PROCEDURE__', '__OUTLET__'], [$procedure, $outlet], $sql);
    }

    private function productProcedureSql(string $procedure, string $outlet): string
    {
        if ($outlet === 'MB') {
            return $this->minibarProductProcedureSql($procedure);
        }

        $sql = <<<'SQL'
CREATE PROCEDURE `__PROCEDURE__`(
    IN p_from_date DATE, IN p_to_date DATE, IN p_shift VARCHAR(20), IN p_department VARCHAR(50),
    IN p_user VARCHAR(50), IN p_freeitem TINYINT, IN p_group_by_date TINYINT
)
READS SQL DATA
BEGIN
    WITH payment_ranked AS (
        SELECT p.payment_id, p.payment_method_id AS PaymentMethod,
            ROW_NUMBER() OVER (PARTITION BY p.payment_id ORDER BY COALESCE(p.legacy_id, p.id), p.id) AS PaymentRank
        FROM payments p WHERE p.payment_id IS NOT NULL
    ), payment_rollup AS (
        SELECT payment_id, PaymentMethod FROM payment_ranked WHERE PaymentRank = 1
    )
    SELECT
        CASE WHEN COALESCE(p_group_by_date, 0) = 1 THEN DATE_FORMAT(MIN(h.Date), '%d/%m/%Y') ELSE NULL END AS DateGroup,
        MIN(d.Product) AS Product, MIN(d.MaProduct) AS MaProduct, MIN(d.ProductGroupId) AS ProductType,
        MIN(h.Currency) AS Currency, SUM(d.Quantity) AS Quantity, AVG(d.Rate) AS Rate,
        SUM(d.Quantity * d.Rate) AS Amount,
        AVG(d.Rate + d.DiscountAmount / NULLIF(d.Quantity, 0)) AS StandardRate,
        SUM(d.TotalAmount) AS Total, SUM(d.DiscountAmount) AS DiscountAmount
    FROM housekeeping_service_bill_details d
    JOIN housekeeping_service_bills h ON h.Ma = d.BillId
    JOIN service_bills sb ON sb.Ma = h.BillServiceId
    LEFT JOIN payment_rollup pay ON pay.payment_id = sb.PaymentId
    WHERE h.Outlet = '__OUTLET__'
      AND h.Date >= p_from_date AND h.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND sb.Status <> 4 AND h.BillEdit = 0 AND d.Deleted = 0
      AND (p_user IS NULL OR p_user = '' OR h.BillUsername COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_user, '%'))
      AND (p_shift IS NULL OR p_shift = '' OR CAST(h.BillShift AS CHAR) COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_shift, '%'))
      AND (p_department IS NULL OR p_department = '' OR h.Department COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_department, '%'))
      AND (
          (COALESCE(p_freeitem, 0) = 0 AND COALESCE(h.FOCType, 0) = 0 AND (pay.PaymentMethod IS NULL OR pay.PaymentMethod <> 'CL'))
          OR (COALESCE(p_freeitem, 0) = 1 AND (COALESCE(h.FOCType, 0) <> 0 OR pay.PaymentMethod = 'CL'))
      )
    GROUP BY CASE WHEN COALESCE(p_group_by_date, 0) = 1 THEN DATE(h.Date) ELSE NULL END,
             d.MaProduct, d.ProductGroupId, d.Product
    HAVING SUM(d.Quantity) > 0
    ORDER BY Product;
END
SQL;

        return str_replace(['__PROCEDURE__', '__OUTLET__'], [$procedure, $outlet], $sql);
    }

    private function minibarProductProcedureSql(string $procedure): string
    {
        return str_replace('__PROCEDURE__', $procedure, <<<'SQL'
CREATE PROCEDURE `__PROCEDURE__`(
    IN p_from_date DATE, IN p_to_date DATE, IN p_shift VARCHAR(20), IN p_department VARCHAR(50),
    IN p_user VARCHAR(50), IN p_freeitem TINYINT, IN p_group_by_date TINYINT
)
READS SQL DATA
BEGIN
    WITH payment_ranked AS (
        SELECT p.payment_id, p.payment_method_id AS PaymentMethod,
            ROW_NUMBER() OVER (PARTITION BY p.payment_id ORDER BY COALESCE(p.legacy_id, p.id), p.id) AS PaymentRank
        FROM payments p WHERE p.payment_id IS NOT NULL
    ), payment_rollup AS (
        SELECT payment_id, PaymentMethod FROM payment_ranked WHERE PaymentRank = 1
    )
    SELECT ROW_NUMBER() OVER (ORDER BY sub.DateGroup, sub.ProductType, sub.Product) AS ID,
           sub.DateGroup, sub.ProductType, sub.MaProduct, sub.Product, sub.Currency,
           sub.Quantity, sub.Rate, sub.Amount, sub.DiscountAmount, sub.Total
    FROM (
        SELECT CASE WHEN COALESCE(p_group_by_date, 0) = 1 THEN DATE_FORMAT(MIN(h.Date), '%d/%m/%Y') ELSE NULL END AS DateGroup,
               COALESCE(MIN(c.name), 'Minibar') AS ProductType, MIN(d.MaProduct) AS MaProduct,
               MIN(d.Product) AS Product, MIN(h.Currency) AS Currency, SUM(d.Quantity) AS Quantity,
               AVG(d.Rate) AS Rate, SUM(d.Quantity * d.Rate) AS Amount,
               SUM(d.DiscountAmount) AS DiscountAmount, SUM(d.TotalAmount) AS Total
        FROM housekeeping_service_bill_details d
        JOIN housekeeping_service_bills h ON h.Ma = d.BillId
        JOIN service_bills sb ON sb.Ma = h.BillServiceId
        LEFT JOIN payment_rollup pay ON pay.payment_id = sb.PaymentId
        LEFT JOIN product_categories c ON c.id = d.ProductGroupId
        WHERE h.Outlet = 'MB'
          AND h.Date >= p_from_date AND h.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND sb.Status <> 4 AND h.BillEdit = 0 AND d.Deleted = 0
          AND (p_user IS NULL OR p_user = '' OR h.BillUsername COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_user, '%'))
          AND (p_shift IS NULL OR p_shift = '' OR CAST(h.BillShift AS CHAR) COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_shift, '%'))
          AND (p_department IS NULL OR p_department = '' OR h.Department COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', p_department, '%'))
          AND ((COALESCE(p_freeitem, 0) = 0 AND COALESCE(h.FOCType, 0) = 0 AND (pay.PaymentMethod IS NULL OR pay.PaymentMethod <> 'CL'))
               OR (COALESCE(p_freeitem, 0) = 1 AND (COALESCE(h.FOCType, 0) <> 0 OR pay.PaymentMethod = 'CL')))
        GROUP BY CASE WHEN COALESCE(p_group_by_date, 0) = 1 THEN DATE(h.Date) ELSE NULL END,
                 COALESCE(c.name, 'Minibar'), d.MaProduct, d.Product
        HAVING SUM(d.Quantity) > 0
    ) sub
    ORDER BY sub.DateGroup, sub.ProductType, sub.Product;
END
SQL);
    }

    private function freeProcedureSql(string $procedure, string $outlet): string
    {
        $sql = <<<'SQL'
CREATE PROCEDURE `__PROCEDURE__`(
    IN p_from_date DATE, IN p_to_date DATE, IN p_user VARCHAR(50),
    IN p_order_by VARCHAR(30), IN p_order_type VARCHAR(4), IN p_show_details TINYINT
)
READS SQL DATA
BEGIN
    WITH product_lines AS (
        SELECT d.BillId, TRIM(d.Product) AS Product, SUM(d.Quantity) AS Quantity, SUM(d.TotalAmount) AS TotalAmount
        FROM housekeeping_service_bill_details d
        WHERE d.Deleted = 0
        GROUP BY d.BillId, TRIM(d.Product)
    ), product_rollup AS (
        SELECT BillId,
            GROUP_CONCAT(CONCAT('* ', Product, ' - ', CAST(Quantity AS CHAR)) ORDER BY Product SEPARATOR '') AS Product,
            CONCAT('[', GROUP_CONCAT(JSON_OBJECT('Product', Product, 'Quantity', Quantity, 'TotalAmount', TotalAmount) ORDER BY Product SEPARATOR ','), ']') AS ProductItems
        FROM product_lines WHERE NULLIF(Product, '') IS NOT NULL GROUP BY BillId
    ), payment_ranked AS (
        SELECT p.payment_id, p.payment_method_id AS PaymentMethod,
            ROW_NUMBER() OVER (PARTITION BY p.payment_id ORDER BY COALESCE(p.legacy_id, p.id), p.id) AS PaymentRank
        FROM payments p WHERE p.payment_id IS NOT NULL
    ), payment_rollup AS (
        SELECT payment_id, PaymentMethod FROM payment_ranked WHERE PaymentRank = 1
    ), report_rows AS (
        SELECT ROW_NUMBER() OVER (ORDER BY
                CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC' THEN h.Ma END ASC,
                CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC' THEN h.Ma END DESC
            ) AS STT,
            COALESCE(h.BookingId, sb.RegisterID2, sb.RegisterId1) AS BookingId,
            COALESCE(h.NumOfRoom, h.RoomNo) AS Room,
            COALESCE(NULLIF(sb.Guest, ''), NULLIF(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), ''), NULLIF(h.GuestId, '')) AS Guest,
            sb.DescriptionServive, COALESCE(pr.Product, '') AS Product,
            h.BillOriginalAmount AS TotalAmount, h.BillDiscountAmount AS DiscountAmount,
            COALESCE(h.BillTotalAmount, h.BillAmount) AS NetAmount, sb.PaymentId AS PaymentID,
            h.BillNote, h.BillUsername AS Username, CAST(h.BillShift AS CHAR) AS Ca,
            COALESCE(pay.PaymentMethod, '') AS HTTT, DATE_FORMAT(h.Date, '%d/%m/%Y') AS DateGroup,
            'Free' AS Status, COALESCE(pr.ProductItems, JSON_ARRAY()) AS ProductItems,
            SUM(h.BillOriginalAmount) OVER () AS ReportTotalAmount,
            SUM(h.BillDiscountAmount) OVER () AS ReportDiscountAmount,
            SUM(COALESCE(h.BillTotalAmount, h.BillAmount)) OVER () AS ReportNetAmount
        FROM housekeeping_service_bills h
        LEFT JOIN service_bills sb ON sb.Ma = h.BillServiceId
        LEFT JOIN product_rollup pr ON pr.BillId = h.Ma
        JOIN payment_rollup pay ON pay.payment_id = sb.PaymentId
        LEFT JOIN guests g ON g.id = h.GuestId
        WHERE h.Date >= p_from_date AND h.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND h.Outlet = '__OUTLET__' AND pay.PaymentMethod = 'CL'
          AND (p_user IS NULL OR p_user = '' OR h.BillUsername LIKE CONCAT('%', p_user, '%'))
    )
    SELECT STT, BookingId, Room, Guest, DescriptionServive, Product, TotalAmount, DiscountAmount, NetAmount,
           PaymentID, BillNote, Username, Ca, HTTT, DateGroup, Status, ProductItems,
           ReportTotalAmount, ReportDiscountAmount, ReportNetAmount
    FROM report_rows ORDER BY STT;
END
SQL;

        return str_replace(['__PROCEDURE__', '__OUTLET__'], [$procedure, $outlet], $sql);
    }

    private function updateFields(string $code, array $fields): void
    {
        DB::table('report_data_sources')->where('code', $code)->update([
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function applyProductUiContract(string $code): void
    {
        $this->updateUiSchema($code, static function (array $parameter): array {
            if (($parameter['name'] ?? '') === 'p_freeitem') {
                $parameter['control'] = 'pill-toggle';
                $parameter['inactive_label'] = 'Hàng bán';
                $parameter['active_label'] = 'Hàng miễn phí';
                $parameter['default'] = 0;
            }

            return $parameter;
        });
    }

    private function applyFreeUiContract(string $code): void
    {
        $allowed = ['p_from_date', 'p_to_date', 'p_user', 'p_order_by', 'p_order_type', 'p_show_details'];
        $this->updateUiSchema($code, static function (array $parameter): array {
            if (($parameter['name'] ?? '') === 'p_order_by') {
                $parameter['options'] = [['value' => 'Ma', 'label' => 'Mã']];
                $parameter['default'] = 'Ma';
            }

            return $parameter;
        }, $allowed);
    }

    private function updateUiSchema(string $code, callable $transform, ?array $allowed = null): void
    {
        $definition = DB::table('report_definitions')->where('code', $code)->first(['id', 'parameter_ui_schema']);
        if (! $definition) {
            return;
        }

        $schema = json_decode($definition->parameter_ui_schema ?: '[]', true);
        if (! is_array($schema)) {
            return;
        }

        $schema = array_values(array_map($transform, array_filter($schema, static fn (array $parameter): bool => $allowed === null || in_array($parameter['name'] ?? '', $allowed, true))));
        DB::table('report_definitions')->where('id', $definition->id)->update([
            'parameter_ui_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function syncTemplate(string $report, string $providerFile): void
    {
        $definition = (require database_path('report_templates/'.$providerFile))->definition();
        DB::table('templates')->where('report', $report)->update([
            'content_html' => $definition['content_html'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'css' => $definition['css'],
            'updated_at' => now(),
        ]);
    }

    private function normalFields(): array
    {
        return $this->fields(['STT', 'BookingId', 'Room', 'Guest', 'DescriptionServive', 'Product', 'TotalAmount', 'DiscountAmount', 'NetAmount', 'PaymentID', 'BillNote', 'Username', 'Ca', 'HTTT', 'DateGroup', 'Status', 'ProductItems', 'ReportTotalAmount', 'ReportDiscountAmount', 'ReportNetAmount'], ['STT', 'TotalAmount', 'DiscountAmount', 'NetAmount', 'ReportTotalAmount', 'ReportDiscountAmount', 'ReportNetAmount']);
    }

    private function freeFields(): array
    {
        return $this->normalFields();
    }

    private function productFields(): array
    {
        return $this->fields(['DateGroup', 'Product', 'MaProduct', 'ProductType', 'Currency', 'Quantity', 'Rate', 'Amount', 'StandardRate', 'Total', 'DiscountAmount'], ['MaProduct', 'ProductType', 'Quantity', 'Rate', 'Amount', 'StandardRate', 'Total', 'DiscountAmount']);
    }

    private function minibarProductFields(): array
    {
        return $this->fields(['ID', 'DateGroup', 'ProductType', 'MaProduct', 'Product', 'Currency', 'Quantity', 'Rate', 'Amount', 'DiscountAmount', 'Total'], ['ID', 'MaProduct', 'Quantity', 'Rate', 'Amount', 'DiscountAmount', 'Total']);
    }

    private function fields(array $names, array $numeric): array
    {
        return array_map(static fn (string $name): array => [
            'name' => $name,
            'type' => in_array($name, $numeric, true) ? ($name === 'STT' ? 'integer' : 'number') : 'string',
            'nullable' => $name !== 'STT',
        ], $names);
    }

    public function down(): void
    {
        // Kept intentionally: rolling back would restore known-incomplete report contracts.
    }
};
