<?php

namespace App\Services\Reports;

use InvalidArgumentException;

/** Builds the secondary bands used by the COMPANY_DEBT legacy layout. */
final class CompanyDebtDataAdapter
{
    public function adapt(array $result): array
    {
        foreach (['rows', 'company_summary', 'settlement_details', 'totals'] as $key) {
            if (array_key_exists($key, $result) && ! is_array($result[$key])) {
                throw new InvalidArgumentException("Company debt {$key} must be an array.");
            }
        }

        $rows = array_values($result['rows'] ?? []);
        $companySummary = $result['company_summary'] ?? $this->companySummary($rows);
        $settlements = $result['settlement_details'] ?? $this->settlements($rows);
        $totals = $result['totals'] ?? $this->totals($companySummary);

        $technicalFields = ['PaidAmount', 'RemainingAmount', 'SettlementItems'];
        $result['rows'] = $this->escapeValue(array_map(static function (array $row) use ($technicalFields): array {
            foreach ($technicalFields as $field) {
                unset($row[$field]);
            }

            return $row;
        }, $rows));
        $result['company_summary'] = $this->escapeValue(array_values($companySummary));
        $result['settlement_details'] = $this->escapeValue(array_values($settlements));
        $result['totals'] = $totals;
        $result['fields'] = array_values(array_filter(
            $result['fields'] ?? [],
            static fn (mixed $field): bool => ! in_array(is_array($field) ? ($field['name'] ?? '') : $field, $technicalFields, true)
        ));

        return $result;
    }

    private function companySummary(array $rows): array
    {
        $companies = [];
        foreach ($rows as $row) {
            $key = (string) ($row['CompanyId'] ?? $row['CompanyName'] ?? '');
            $companies[$key] ??= [
                'CompanyName' => $row['CompanyName'] ?? '',
                'TotalAmount' => 0.0,
                'PaidAmount' => 0.0,
                'RemainingAmount' => 0.0,
            ];
            $companies[$key]['TotalAmount'] += (float) ($row['TotalAmount'] ?? 0);
            $companies[$key]['PaidAmount'] += (float) ($row['PaidAmount'] ?? 0);
            $companies[$key]['RemainingAmount'] += (float) ($row['RemainingAmount'] ?? 0);
        }

        uasort($companies, static fn (array $left, array $right): int => strnatcasecmp(
            (string) $left['CompanyName'],
            (string) $right['CompanyName']
        ));

        return array_values($companies);
    }

    private function settlements(array $rows): array
    {
        $settlements = [];
        foreach ($rows as $row) {
            $items = $row['SettlementItems'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $settlementId = (string) ($item['SettlementId'] ?? '');
                $key = implode('|', [(string) ($row['PaymentId'] ?? ''), $settlementId]);
                $settlements[$key] = [
                    'BookingId' => $row['BookingId'] ?? '',
                    'CompanyName' => $row['CompanyName'] ?? '',
                    'PaymentId' => $row['PaymentId'] ?? '',
                    'SettlementId' => $item['SettlementId'] ?? '',
                    'Date' => $item['Date'] ?? '',
                    'Reference' => $item['Reference'] ?? '',
                    'Amount' => $item['Amount'] ?? 0,
                ];
            }
        }

        return array_values($settlements);
    }

    private function totals(array $companySummary): array
    {
        return [
            'TotalAmount' => array_sum(array_column($companySummary, 'TotalAmount')),
            'PaidAmount' => array_sum(array_column($companySummary, 'PaidAmount')),
            'RemainingAmount' => array_sum(array_column($companySummary, 'RemainingAmount')),
        ];
    }

    private function escapeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->escapeValue($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return strtr($escaped, ['{' => '&#123;', '}' => '&#125;']);
    }
}
