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

            $row = $connection->selectOne('SHOW CREATE PROCEDURE rpt_expected_breakfast_1');
            $values = $row ? (array) $row : [];
            $createSql = null;
            foreach ($values as $key => $value) {
                if (strtolower((string) $key) === 'create procedure') {
                    $createSql = (string) $value;
                    break;
                }
            }
            if (! $createSql) {
                throw new RuntimeException('Không tìm thấy rpt_expected_breakfast_1.');
            }

            if (str_contains($createSql, '0 AS breakfast_adult_amount')) {
                continue;
            }

            $createSql = str_replace(
                "        '' AS detail_room\n    FROM tmp_active_rooms ar",
                "        0 AS breakfast_adult_amount,\n        0 AS breakfast_child_amount,\n        0 AS breakfast_child_extra_amount,\n        0 AS breakfast_total_amount,\n        '' AS detail_room\n    FROM tmp_active_rooms ar",
                $createSql,
                $replacementCount
            );
            if ($replacementCount !== 1) {
                throw new RuntimeException("Không sửa được dòng insert của {$connectionName}.");
            }

            $connection->unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast_1`');
            $connection->unprepared($createSql);
        }
    }

    public function down(): void
    {
        // The previous procedure definition is retained by the migration history.
    }
};
