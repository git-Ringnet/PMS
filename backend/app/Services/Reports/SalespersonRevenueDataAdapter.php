<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

final class SalespersonRevenueDataAdapter
{
    public function adapt(array $result): array
    {
        $values = collect($result['rows'] ?? [])
            ->pluck('SalesPersonName')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return $result;
        }

        $names = DB::connection(config('database_domains.system_connection', 'mysql_system'))
            ->table('users')
            ->whereIn('username', $values->all())
            ->pluck('name', 'username');

        foreach ($result['rows'] as &$row) {
            $fallback = trim((string) ($row['SalesPersonName'] ?? ''));
            $row['SalesPersonName'] = $names->get($fallback)
                ?: ($fallback !== '' ? $fallback : 'Chưa phân công');
        }
        unset($row);

        return $result;
    }
}
