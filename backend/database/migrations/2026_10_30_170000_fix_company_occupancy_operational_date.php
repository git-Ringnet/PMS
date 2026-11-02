<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::getDefaultConnection();
        $db = DB::connection($connection);
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $exists = $db->selectOne(
            'SELECT 1 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?',
            ['rpt_company_occupancy', 'PROCEDURE']
        );
        if (! $exists) {
            return;
        }

        $definition = $db->selectOne('SHOW CREATE PROCEDURE `rpt_company_occupancy`');
        $procedure = (string) ($definition->{'Create Procedure'} ?? '');
        if (stripos($procedure, 'system_date_rolls') !== false) {
            return;
        }

        $updatedProcedure = str_replace($this->legacySystemDateBlock(), $this->operationalDateBlock(), $procedure);
        if ($updatedProcedure === $procedure) {
            $normProc = str_replace(["\r\n", "\r"], "\n", $procedure);
            $normLegacy = str_replace(["\r\n", "\r"], "\n", $this->legacySystemDateBlock());
            $normOp = str_replace(["\r\n", "\r"], "\n", $this->operationalDateBlock());
            if (str_contains($normProc, $normLegacy)) {
                $updatedProcedure = str_replace($normLegacy, $normOp, $normProc);
            }
        }

        if ($updatedProcedure !== $procedure) {
            $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_company_occupancy`');
            $db->unprepared($updatedProcedure);
        }
    }

    public function down(): void
    {
        // Không khôi phục fallback ngày máy chủ vì ngày vận hành PMS là hành vi đúng.
    }

    private function legacySystemDateBlock(): string
    {
        return <<<'SQL'
    -- Xác định ngày hệ thống
    SELECT COALESCE(CAST(value AS DATE), CURRENT_DATE()) INTO v_system_date
      FROM hotel_configs
     WHERE LOWER(name) = 'systemdate' AND value IS NOT NULL AND value <> ''
     LIMIT 1;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;
SQL;
    }

    private function operationalDateBlock(): string
    {
        return <<<'SQL'
    -- Xác định ngày hệ thống theo ngày vận hành PMS.
    -- Ưu tiên cấu hình, sau đó là lịch sử Sang Ngày; chỉ cuối cùng mới dùng ngày máy chủ.
    SET v_system_date = NULL;
    SELECT CAST(value AS DATE) INTO v_system_date
      FROM hotel_configs
     WHERE LOWER(name) = 'systemdate' AND value IS NOT NULL AND value <> ''
     LIMIT 1;
    IF v_system_date IS NULL THEN
        SELECT DATE(system_date) INTO v_system_date
          FROM system_date_rolls
         ORDER BY id DESC
         LIMIT 1;
    END IF;
    IF v_system_date IS NULL THEN SET v_system_date = CURRENT_DATE(); END IF;
SQL;
    }
};
