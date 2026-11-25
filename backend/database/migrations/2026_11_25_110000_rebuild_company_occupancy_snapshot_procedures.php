<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_MIGRATION = '2026_11_23_140000_harmonize_company_occupancy_runtime_procedures.php';

    public function up(): void
    {
        $connection = DB::connection(DB::getDefaultConnection());
        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $source = require database_path('migrations/'.self::SOURCE_MIGRATION);
        foreach ([
            'rpt_company_occupancy' => 'summaryProcedure',
            'rpt_company_occupancy_detail' => 'detailProcedure',
        ] as $procedure => $sourceMethod) {
            $method = new \ReflectionMethod($source, $sourceMethod);
            $method->setAccessible(true);
            $sql = $method->invoke($source);
            if (! is_string($sql) || $sql === '') {
                throw new \RuntimeException("Không tạo được SQL cho {$procedure} từ contract TC_BC_LT_02.");
            }

            $connection->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
            $connection->unprepared($sql);
        }
    }

    public function down(): void
    {
        // Không hoàn nguyên về procedure legacy tính trực tiếp từ service bills.
    }
};
