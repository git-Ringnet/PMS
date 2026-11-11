<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL 8 (ER_FIELD_IN_ORDER_NOT_SELECT, 3065) không cho SELECT DISTINCT ... ORDER BY cột không nằm trong
 * danh sách SELECT => báo cáo công nợ công ty báo "Unable to execute the report stored procedure".
 * Tạo lại thủ tục theo SQL đã sửa (bảng con có cột phụ để sắp xếp), dưới collation unicode_ci.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $original = require __DIR__.'/2026_09_10_260000_create_company_debt_report.php';
        $pdo = $connection->getPdo();
        $previous = $connection->selectOne('SELECT @@collation_connection AS c')->c ?? 'utf8mb4_unicode_ci';
        $pdo->exec('SET collation_connection = utf8mb4_unicode_ci');

        try {
            $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt');
            $connection->unprepared($original->procedureSql());
        } finally {
            $pdo->exec('SET collation_connection = '.$previous);
        }
    }

    public function down(): void
    {
        // Không cần hoàn tác.
    }
};
