<?php

namespace App\Services\Reports;

use InvalidArgumentException;

/**
 * Boundary adapter for the LA/BR/MB housekeeping invoice reports.
 *
 * Filtering and money semantics stay in the report procedure. This adapter
 * removes its technical aggregate fields and expands the product payload for
 * the two tables used by the legacy layout.
 */
final class HousekeepingInvoiceDataAdapter
{
    public const OUTLETS = ['LA', 'BR', 'MB'];

    public const ROW_FIELDS = [
        'STT', 'BookingId', 'Room', 'Guest', 'DescriptionServive', 'Product',
        'TotalAmount', 'DiscountAmount', 'NetAmount', 'PaymentID', 'BillNote',
        'Username', 'Ca', 'DateGroup',
    ];

    public function adapt(string $outlet, array $result): array
    {
        $outlet = strtoupper(trim($outlet));
        if (! in_array($outlet, self::OUTLETS, true)) {
            throw new InvalidArgumentException('Unsupported housekeeping invoice outlet.');
        }

        foreach (['rows', 'product_summary', 'totals'] as $key) {
            if (array_key_exists($key, $result) && ! is_array($result[$key])) {
                throw new InvalidArgumentException("Housekeeping report {$key} must be an array.");
            }
        }

        $rows = array_values($result['rows'] ?? []);
        $totals = $result['totals'] ?? [];
        if ($rows !== [] && $totals === []) {
            $totals = [
                'TotalAmount' => $rows[0]['ReportTotalAmount'] ?? 0,
                'DiscountAmount' => $rows[0]['ReportDiscountAmount'] ?? 0,
                'NetAmount' => $rows[0]['ReportNetAmount'] ?? 0,
            ];
        }

        $productSummary = array_values($result['product_summary'] ?? []);
        if ($productSummary === []) {
            $productSummary = $this->productSummary($rows);
        }

        $technicalFields = ['ProductItems', 'ReportTotalAmount', 'ReportDiscountAmount', 'ReportNetAmount'];
        $rows = array_map(static function (array $row) use ($technicalFields): array {
            foreach ($technicalFields as $field) {
                unset($row[$field]);
            }

            return $row;
        }, $rows);

        $result['outlet'] = $outlet;
        $result['parameters'] = is_array($result['parameters'] ?? null) ? $result['parameters'] : [];
        $result['rows'] = $this->escapeValue($rows);
        $result['product_summary'] = $this->escapeValue($productSummary);
        $result['totals'] = $totals;
        $result['fields'] = array_values(array_filter(
            $result['fields'] ?? [],
            static fn (mixed $field): bool => ! in_array(is_array($field) ? ($field['name'] ?? '') : $field, $technicalFields, true)
        ));
        $result['summary'] = is_array($result['summary'] ?? null) ? $result['summary'] : [];

        return $result;
    }

    public function empty(string $outlet, array $parameters = []): array
    {
        return $this->adapt($outlet, [
            'parameters' => $parameters,
            'rows' => [],
            'product_summary' => [],
            'totals' => [],
        ]);
    }

    private function productSummary(array $rows): array
    {
        $products = [];
        foreach ($rows as $row) {
            $items = $row['ProductItems'] ?? [];
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
                $name = trim((string) ($item['Product'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $products[$name] ??= ['Product' => $name, 'Quantity' => 0.0, 'TotalAmount' => 0.0];
                $products[$name]['Quantity'] += (float) ($item['Quantity'] ?? 0);
                $products[$name]['TotalAmount'] += (float) ($item['TotalAmount'] ?? 0);
            }
        }

        ksort($products, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($products);
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
