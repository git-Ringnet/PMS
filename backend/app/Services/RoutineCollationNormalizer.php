<?php

namespace App\Services;

use Illuminate\Database\Connection;

/**
 * Tạo lại thủ tục/hàm đang lưu collation_connection khác utf8mb4_unicode_ci (thường do clone/import database
 * bằng client mặc định general_ci) để tránh lỗi "Illegal mix of collations" khi chạy báo cáo rpt_*.
 */
class RoutineCollationNormalizer
{
    public const COLLATION = 'utf8mb4_unicode_ci';

    /** @return int số thủ tục/hàm đã tạo lại */
    public function normalize(Connection $connection): int
    {
        if ($connection->getDriverName() !== 'mysql') {
            return 0;
        }

        $routines = $connection->select(
            'SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND COLLATION_CONNECTION <> ?',
            [self::COLLATION]
        );
        if (! $routines) {
            return 0;
        }

        $pdo = $connection->getPdo();
        $previous = $connection->selectOne('SELECT @@collation_connection AS c')->c ?? self::COLLATION;
        $pdo->exec('SET collation_connection = '.self::COLLATION);

        $count = 0;
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
                $count++;
            }
        } finally {
            $pdo->exec('SET collation_connection = '.$previous);
        }

        return $count;
    }
}
