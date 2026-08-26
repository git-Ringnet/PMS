<?php

namespace App\Services\Reports;

use App\Models\ReportDataSource;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class ReportDataExecutorService
{
    public function __construct(private readonly ReportProcedureCatalogService $catalog) {}

    public function executeSource(ReportDataSource $source, array $parameters): array
    {
        if (! $source->is_active) {
            throw new InvalidArgumentException('Report data source is inactive.');
        }

        if ($source->source_type !== 'procedure') {
            throw new InvalidArgumentException('Unsupported report data source type.');
        }

        return $this->executeProcedure(
            $source->object_name,
            $source->parameter_schema ?? [],
            $parameters,
            $source->max_rows
        );
    }

    public function executeProcedure(
        string $objectName,
        array $parameterSchema,
        array $parameters,
        ?int $maxRows = null
    ): array {
        $this->catalog->assertIdentifier($objectName);
        [$placeholders, $bindings] = $this->prepareBindings($parameterSchema, $parameters);
        $maxRows = $this->normalizeMaxRows($maxRows);
        $quotedName = '`'.str_replace('`', '``', $objectName).'`';
        $sql = 'CALL '.$quotedName.'('.implode(', ', $placeholders).')';
        $statement = null;

        try {
            $statement = $this->connection()->getPdo()->prepare($sql);
            $statement->execute($bindings);
            $rows = [];
            $truncated = false;

            do {
                if ($statement->columnCount() === 0) {
                    continue;
                }

                while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    if (count($rows) >= $maxRows) {
                        $truncated = true;
                        break 2;
                    }
                    $rows[] = $row;
                }

                // MVP intentionally exposes only the first tabular result set.
                break;
            } while ($statement->nextRowset());

            $fields = $this->discoverFields($rows, $statement);

            return [
                'parameters' => $parameters,
                'rows' => $rows,
                'summary' => [
                    'row_count' => count($rows),
                    'truncated' => $truncated,
                ],
                'fields' => $fields,
            ];
        } catch (Throwable $exception) {
            report($exception);
            throw new RuntimeException('Unable to execute the report stored procedure.', 0, $exception);
        } finally {
            if ($statement) {
                try {
                    while ($statement->nextRowset()) {
                        // Drain MySQL result sets so the connection remains reusable.
                    }
                    $statement->closeCursor();
                } catch (Throwable) {
                    // The original execution error is more useful than a cursor cleanup error.
                }
            }
        }
    }

    private function prepareBindings(array $schema, array $parameters): array
    {
        $placeholders = [];
        $bindings = [];

        foreach ($schema as $definition) {
            $name = (string) ($definition['name'] ?? '');
            $mode = strtoupper((string) ($definition['mode'] ?? 'IN'));
            if ($name === '' || $mode !== 'IN') {
                throw new InvalidArgumentException('Only named IN parameters are supported in report procedures.');
            }
            if (! array_key_exists($name, $parameters)) {
                throw new InvalidArgumentException("Missing report parameter: {$name}.");
            }

            $placeholders[] = '?';
            $bindings[] = $this->castValue($parameters[$name], (string) ($definition['data_type'] ?? 'varchar'));
        }

        return [$placeholders, $bindings];
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return $value === '' ? null : $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return match (strtolower($type)) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw new InvalidArgumentException('Invalid integer report parameter.'),
            'decimal', 'numeric', 'float', 'double', 'real' => is_numeric($value)
                ? (string) $value
                : throw new InvalidArgumentException('Invalid numeric report parameter.'),
            default => (string) $value,
        };
    }

    private function discoverFields(array $rows, $statement): array
    {
        if ($rows !== []) {
            $sample = $rows[0];

            return array_map(static function (string $name) use ($sample) {
                $value = $sample[$name] ?? null;

                return [
                    'name' => $name,
                    'type' => match (true) {
                        is_int($value) => 'integer',
                        is_float($value) => 'number',
                        is_bool($value) => 'boolean',
                        default => 'string',
                    },
                    'nullable' => $value === null,
                ];
            }, array_keys($sample));
        }

        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); $index++) {
            $meta = $statement->getColumnMeta($index) ?: [];
            $fields[] = [
                'name' => $meta['name'] ?? "column_{$index}",
                'type' => strtolower((string) ($meta['native_type'] ?? 'string')),
                'nullable' => true,
            ];
        }

        return $fields;
    }

    private function normalizeMaxRows(?int $maxRows): int
    {
        $default = max(1, (int) config('reporting.default_max_rows', 1000));
        $maximum = max($default, (int) config('reporting.maximum_max_rows', 5000));

        return min(max(1, $maxRows ?? $default), $maximum);
    }

    private function connection(): Connection
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Stored procedure reports require a MySQL branch connection.');
        }

        return $connection;
    }
}
