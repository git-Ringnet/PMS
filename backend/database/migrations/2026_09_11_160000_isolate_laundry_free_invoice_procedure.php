<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS `rpt_laundry_free_invoices`');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE `rpt_laundry_free_invoices`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(50),
    IN p_order_by VARCHAR(30),
    IN p_order_type VARCHAR(4),
    IN p_show_details TINYINT
)
READS SQL DATA
BEGIN
    WITH product_lines AS (
        SELECT d.BillId, TRIM(d.Product) AS Product, SUM(d.Quantity) AS Quantity,
               SUM(d.TotalAmount) AS TotalAmount
        FROM housekeeping_service_bill_details d
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
    )
    SELECT ROW_NUMBER() OVER (
               ORDER BY CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'ASC' THEN h.Ma END DESC,
                        CASE WHEN UPPER(COALESCE(p_order_type, 'ASC')) = 'DESC' THEN h.Ma END ASC
           ) AS STT,
           COALESCE(h.BookingId, sb.RegisterID2, sb.RegisterId1) AS BookingId,
           COALESCE(h.NumOfRoom, h.RoomNo) AS Room,
           COALESCE(NULLIF(sb.Guest, ''), NULLIF(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), ''), NULLIF(h.GuestId, '')) AS Guest,
           sb.DescriptionServive, COALESCE(pr.Product, '') AS Product,
           h.BillOriginalAmount AS TotalAmount, h.BillDiscountAmount AS DiscountAmount,
           COALESCE(h.BillTotalAmount, h.BillAmount) AS NetAmount, sb.PaymentId AS PaymentID,
           h.BillNote, h.BillUsername AS Username, CAST(h.BillShift AS CHAR) AS Ca,
           COALESCE(pm.name, pay.PaymentMethod) AS HTTT,
           DATE_FORMAT(h.Date, '%d/%m/%Y') AS DateGroup, COALESCE(pr.ProductItems, JSON_ARRAY()) AS ProductItems,
           SUM(h.BillOriginalAmount) OVER () AS ReportTotalAmount,
           SUM(h.BillDiscountAmount) OVER () AS ReportDiscountAmount,
           SUM(COALESCE(h.BillTotalAmount, h.BillAmount)) OVER () AS ReportNetAmount
    FROM housekeeping_service_bills h
    LEFT JOIN service_bills sb ON sb.Ma = h.BillServiceId
    LEFT JOIN product_rollup pr ON pr.BillId = h.Ma
    LEFT JOIN payment_rollup pay ON pay.payment_id = sb.PaymentId
    LEFT JOIN payment_methods pm ON pm.code = pay.PaymentMethod
    LEFT JOIN guests g ON g.id = h.GuestId
    WHERE h.Date >= p_from_date AND h.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND h.Outlet = 'LA' AND pay.PaymentMethod = 'CL'
      AND (p_user IS NULL OR p_user = '' OR h.BillUsername LIKE CONCAT('%', p_user, '%'))
    ORDER BY STT;
END
SQL);

        DB::table('report_data_sources')->where('code', 'LAUNDRY_FREE_INVOICES')->update([
            'parameter_schema' => json_encode([
                ['name'=>'p_from_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>1,'required'=>true],
                ['name'=>'p_to_date','mode'=>'IN','data_type'=>'date','database_type'=>'date','position'=>2,'required'=>true],
                ['name'=>'p_user','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(50)','position'=>3,'required'=>false],
                ['name'=>'p_order_by','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(30)','position'=>4,'required'=>true],
                ['name'=>'p_order_type','mode'=>'IN','data_type'=>'varchar','database_type'=>'varchar(4)','position'=>5,'required'=>true],
                ['name'=>'p_show_details','mode'=>'IN','data_type'=>'tinyint','database_type'=>'tinyint','position'=>6,'required'=>false],
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        $source = DB::table('report_data_sources')->where('code', 'LAUNDRY_FREE_INVOICES')->first();
        $fields = $source?->field_schema ? json_decode($source->field_schema, true) : [];
        if (is_array($fields)) {
            $fields = array_map(static function (array $field): array {
                if (($field['name'] ?? null) === 'Ca') {
                    $field['name'] = 'HTTT';
                }
                return $field;
            }, $fields);
            DB::table('report_data_sources')->where('code', 'LAUNDRY_FREE_INVOICES')->update([
                'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        $provider = require database_path('report_templates/laundry_free_invoices_reference.php');
        $definition = $provider->definition();
        DB::table('templates')->where('report', 'LAUNDRY_FREE_INVOICES_STANDARD')->update([
            'content_html' => $definition['content_html'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'css' => $definition['css'],
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
