<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $this->repairProcedure($db, 'rpt_deposits_summary', function (string $sql): string {
                if (! str_contains($sql, "THEN Amount END DESC")) {
                    return $sql;
                }

                return str_replace(
                    [
                        "THEN Amount END DESC",
                        "THEN Amount END ASC",
                    ],
                    [
                        "THEN (CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0) ELSE COALESCE(p.amount, 0) END) END DESC",
                        "THEN (CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0) ELSE COALESCE(p.amount, 0) END) END ASC",
                    ],
                    $sql
                );
            });

            $this->repairProcedure($db, 'rpt_company_occupancy', function (string $sql): string {
                if (! str_contains($sql, 'FROM tmp_company_occupancy_base')
                    || str_contains($sql, 'grouped_rows.CompanyCode')) {
                    return $sql;
                }

                $pattern = <<<'REGEX'
~    SELECT\s+CASE COALESCE\(p_group_by, 'COMPANY'\).*?    ORDER BY\s+TotalRevenue DESC,\s+CompanyName;\s*~s
REGEX;
                $replacement = <<<'SQL'
    SELECT
        grouped_rows.CompanyCode,
        grouped_rows.CompanyName,
        ROUND(SUM(grouped_rows.RoomNight) * 100 / v_capacity, 2) AS OccupancyRate,
        SUM(grouped_rows.RoomNight) AS RoomNight,
        SUM(grouped_rows.GuestQty) AS GuestQty,
        CASE WHEN SUM(grouped_rows.RoomNight) > 0 THEN ROUND(SUM(grouped_rows.RoomRevenue) / SUM(grouped_rows.RoomNight), 0) ELSE 0 END AS ActualADR,
        CASE WHEN SUM(grouped_rows.RoomNight - grouped_rows.FocRoomNights - grouped_rows.HuRoomNights) > 0 THEN ROUND(SUM(grouped_rows.OriginalAmount) / SUM(grouped_rows.RoomNight - grouped_rows.FocRoomNights - grouped_rows.HuRoomNights), 0) ELSE 0 END AS RackADR,
        SUM(grouped_rows.RoomRevenue) AS RoomRevenue,
        SUM(grouped_rows.FbRevenue) AS FbRevenue,
        SUM(grouped_rows.OtherRevenue) AS OtherRevenue,
        SUM(grouped_rows.RoomRevenue + grouped_rows.FbRevenue + grouped_rows.OtherRevenue) AS TotalRevenue
    FROM (
        SELECT
            CASE COALESCE(p_group_by, 'COMPANY') WHEN 'DATE' THEN DATE_FORMAT(ActivityDate, '%d/%m/%Y') WHEN 'MARKET' THEN MarketCode WHEN 'SOURCE' THEN SourceCode ELSE CompanyCode END AS CompanyCode,
            CASE COALESCE(p_group_by, 'COMPANY') WHEN 'DATE' THEN DATE_FORMAT(ActivityDate, '%d/%m/%Y') WHEN 'MARKET' THEN MarketName WHEN 'SOURCE' THEN SourceName ELSE CompanyName END AS CompanyName,
            RoomNight, GuestQty, FocRoomNights, HuRoomNights, RoomRevenue, FbRevenue, OtherRevenue, OriginalAmount
        FROM tmp_company_occupancy_base
    ) AS grouped_rows
    GROUP BY grouped_rows.CompanyCode, grouped_rows.CompanyName
    ORDER BY TotalRevenue DESC, grouped_rows.CompanyName;
SQL;

                $patched = preg_replace($pattern, $replacement, $sql, 1, $count);
                if ($count !== 1 || $patched === null) {
                    throw new RuntimeException('Không thể cập nhật contract GROUP BY của rpt_company_occupancy.');
                }

                return $patched;
            });

        }
    }

    public function down(): void
    {
        // Procedure hotfixes are intentionally kept when rolling back metadata migrations.
    }

    private function repairProcedure($db, string $procedure, callable $transform): void
    {
        $exists = $db->table('information_schema.routines')
            ->where('routine_schema', $db->getDatabaseName())
            ->where('routine_type', 'PROCEDURE')
            ->where('routine_name', $procedure)
            ->exists();

        if (! $exists) {
            return;
        }

        $row = $db->selectOne('SHOW CREATE PROCEDURE `'.$procedure.'`');
        $values = (array) $row;
        $definition = $values['Create Procedure'] ?? array_values($values)[1] ?? null;
        if (! is_string($definition) || $definition === '') {
            throw new RuntimeException("Không đọc được định nghĩa procedure {$procedure}.");
        }

        $patched = $transform($definition);
        if ($patched === $definition) {
            return;
        }

        $patched = preg_replace('/^CREATE DEFINER=.*? PROCEDURE /', 'CREATE PROCEDURE ', $patched, 1) ?? $patched;
        $db->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
        $db->unprepared($patched);
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }
};
