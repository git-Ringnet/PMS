<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TC_BC_BP_01 (BP dòng 162 / Lễ tân dòng 314): báo cáo hóa đơn minibar (theo sản phẩm)
 * chỉ lấy bill Status = 1 (chưa thanh toán). Bill đã thanh toán có Status = 2
 * (PaymentController) nên bị mất khỏi báo cáo. Đổi sang loại trừ bill hủy (Status = 4),
 * giống báo cáo hóa đơn rpt_minibar_invoices. Bill đã sửa (Status = 3) có chi tiết
 * Deleted = 1 nên vẫn bị loại bởi điều kiện d.Deleted = 0.
 */
return new class extends Migration
{
    private const PROCEDURE = 'rpt_minibar_invoices_by_product';
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $sql = $this->source()->procedureSql();
        $sql = str_replace('AND h.Status = 1', 'AND h.Status <> 4', $sql);

        // Tạo dưới unicode_ci để không dính lỗi "Illegal mix of collations".
        $pdo = DB::connection()->getPdo();
        $previous = DB::selectOne('SELECT @@collation_connection AS c')->c ?? self::COLLATION;
        $pdo->exec('SET collation_connection = '.self::COLLATION);
        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
            DB::unprepared($sql);
        } finally {
            $pdo->exec('SET collation_connection = '.$previous);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        $previous = DB::selectOne('SELECT @@collation_connection AS c')->c ?? self::COLLATION;
        $pdo->exec('SET collation_connection = '.self::COLLATION);
        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
            DB::unprepared($this->source()->procedureSql());
        } finally {
            $pdo->exec('SET collation_connection = '.$previous);
        }
    }

    private function source(): object
    {
        return require database_path('migrations/2026_09_14_220000_create_minibar_invoice_product_report.php');
    }
};
