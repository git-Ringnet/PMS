<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Thủ tục/hàm được tạo khi collation_connection = utf8mb4_general_ci (import, CLI...) sẽ lỗi
 * "Illegal mix of collations (utf8mb4_bin,NONE) and (utf8mb4_unicode_ci,IMPLICIT)" khi so sánh
 * CAST(... AS CHAR)/COALESCE với cột unicode_ci => các báo cáo rpt_* báo
 * "Unable to execute the report stored procedure". Tạo lại dưới unicode_ci.
 */
return new class extends Migration
{
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function up(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $routines = $connection->select(
            'SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND COLLATION_CONNECTION <> ?',
            [self::COLLATION]
        );
        if (! $routines) {
            return;
        }

        $pdo = $connection->getPdo();
        $previous = $connection->selectOne('SELECT @@collation_connection AS c')->c ?? self::COLLATION;
        $pdo->exec('SET collation_connection = '.self::COLLATION);

        try {
            foreach ($routines as $routine) {
                $type = strtoupper($routine->type) === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
                $name = '`'.str_replace('`', '``', $routine->name).'`';
                $row = (array) $connection->selectOne("SHOW CREATE $type $name");
                $sql = $row[$type === 'FUNCTION' ? 'Create Function' : 'Create Procedure'] ?? null;
                if (! $sql) {
                    continue;
                }
                $sql = preg_replace('/^CREATE\s+DEFINER=\S+\s+/i', 'CREATE ', $sql);
                $connection->unprepared("DROP $type IF EXISTS $name");
                $connection->unprepared($sql);
            }
        } finally {
            $pdo->exec('SET collation_connection = '.$previous);
        }
    }

    public function down(): void
    {
        // Không cần hoàn tác.
    }
};
