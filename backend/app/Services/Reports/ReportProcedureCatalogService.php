<?php

namespace App\Services\Reports;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ReportProcedureCatalogService
{
    public function list(?string $search = null): array
    {
        $connection = $this->connection();
        $database = $connection->getDatabaseName();
        $prefix = (string) config('reporting.procedure_prefix', 'rpt_');
        $sql = 'SELECT ROUTINE_SCHEMA AS schema_name, ROUTINE_NAME AS object_name, '
            .'ROUTINE_COMMENT AS description, SQL_DATA_ACCESS AS sql_data_access, CREATED AS created_at, LAST_ALTERED AS updated_at '
            .'FROM INFORMATION_SCHEMA.ROUTINES '
            ."WHERE ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_SCHEMA = ? AND ROUTINE_NAME LIKE ? "
            ."AND SQL_DATA_ACCESS IN ('READS SQL DATA', 'NO SQL')";
        $bindings = [$database, $prefix.'%'];

        if ($search !== null && $search !== '') {
            $sql .= ' AND ROUTINE_NAME LIKE ?';
            $bindings[] = '%'.$search.'%';
        }

        $sql .= ' ORDER BY ROUTINE_NAME';

        return array_map(static fn ($row) => (array) $row, $connection->select($sql, $bindings));
    }

    public function describe(string $objectName): array
    {
        $this->assertIdentifier($objectName);
        $connection = $this->connection();
        $database = $connection->getDatabaseName();
        $prefix = (string) config('reporting.procedure_prefix', 'rpt_');

        if (! str_starts_with($objectName, $prefix)) {
            throw new InvalidArgumentException("Stored procedure must start with {$prefix}.");
        }

        $routine = $connection->selectOne(
            "SELECT ROUTINE_SCHEMA AS schema_name, ROUTINE_NAME AS object_name, ROUTINE_COMMENT AS description,
                    SQL_DATA_ACCESS AS sql_data_access
             FROM INFORMATION_SCHEMA.ROUTINES
             WHERE ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_SCHEMA = ? AND ROUTINE_NAME = ?",
            [$database, $objectName]
        );

        if (! $routine) {
            throw new RuntimeException('Stored procedure not found in the active branch database.');
        }

        if (! in_array(strtoupper((string) $routine->sql_data_access), ['READS SQL DATA', 'NO SQL'], true)) {
            throw new InvalidArgumentException('Report procedures must declare READS SQL DATA or NO SQL.');
        }

        $parameters = $connection->select(
            'SELECT PARAMETER_NAME AS name, PARAMETER_MODE AS mode, DATA_TYPE AS data_type,
                    DTD_IDENTIFIER AS database_type, CHARACTER_MAXIMUM_LENGTH AS max_length,
                    NUMERIC_PRECISION AS numeric_precision, NUMERIC_SCALE AS numeric_scale,
                    ORDINAL_POSITION AS position
             FROM INFORMATION_SCHEMA.PARAMETERS
             WHERE SPECIFIC_SCHEMA = ? AND SPECIFIC_NAME = ? AND PARAMETER_NAME IS NOT NULL
             ORDER BY ORDINAL_POSITION',
            [$database, $objectName]
        );

        return [
            ...(array) $routine,
            'parameters' => array_map(function ($parameter) {
                $item = (array) $parameter;
                $item['required'] = strtoupper((string) $item['mode']) === 'IN';

                return $item;
            }, $parameters),
        ];
    }

    public function assertIdentifier(string $identifier): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $identifier)) {
            throw new InvalidArgumentException('Invalid stored procedure identifier.');
        }
    }

    private function connection(): Connection
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Report procedure discovery requires a MySQL branch connection.');
        }

        return $connection;
    }
}
