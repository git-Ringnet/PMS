<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            foreach (['rpt_expected_breakfast_1', 'rpt_expected_breakfast_2'] as $procedureName) {
                $row = $connection->selectOne('SHOW CREATE PROCEDURE `'.$procedureName.'`');
                $values = $row ? (array) $row : [];
                $createSql = null;
                foreach ($values as $key => $value) {
                    if (strtolower((string) $key) === 'create procedure') {
                        $createSql = (string) $value;
                        break;
                    }
                }
                if (! $createSql) {
                    throw new RuntimeException("Không tìm thấy {$procedureName}.");
                }

                if (! str_contains($createSql, "br.arrival_time <= '00:01'")) {
                    continue;
                }

                $createSql = str_replace("br.arrival_time <= '00:01'", "br.arrival_time <= '09:30'", $createSql, $replacementCount);
                if ($replacementCount < 1) {
                    throw new RuntimeException("Không sửa được giờ check-in của {$procedureName}.");
                }

                $connection->unprepared('DROP PROCEDURE IF EXISTS `'.$procedureName.'`');
                $connection->unprepared($createSql);
            }
        }
    }

    public function down(): void
    {
        // The legacy-correct threshold is intentionally retained on rollback.
    }
};
