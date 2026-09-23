<?php

namespace App\Services\Reports;

/**
 * Builds the DEPOSITS_SALE currency allocation by payment method and cashier.
 */
final class DepositsSaleDataAdapter
{
    private const AMOUNT_FIELDS = [
        'CashAmount',
        'DepositAmount',
        'PositiveAmount',
        'RefundAmount',
        'Amount',
    ];

    public function adapt(array $data): array
    {
        $groups = [];

        foreach ($data['rows'] ?? [] as $sourceRow) {
            $row = (array) $sourceRow;
            $methodCode = strtoupper(trim((string) ($row['PaymentMethod'] ?? '')));
            $cashierCode = trim((string) ($row['Username'] ?? ''));
            $key = serialize([$methodCode, $cashierCode]);

            if (! isset($groups[$key])) {
                $methodName = trim((string) ($row['PaymentMethodName'] ?? ''));
                $groups[$key] = [
                    'PaymentMethod' => $methodCode,
                    'PaymentMethodName' => $methodName !== '' ? $methodName : ($methodCode !== '' ? $methodCode : 'Không xác định'),
                    'CashierCode' => $cashierCode,
                    ...array_fill_keys(self::AMOUNT_FIELDS, 0.0),
                ];
            }

            foreach (self::AMOUNT_FIELDS as $field) {
                $groups[$key][$field] += (float) ($row[$field] ?? 0);
            }
        }

        $allocations = array_values($groups);
        usort($allocations, static fn (array $left, array $right): int =>
            [$left['PaymentMethod'], $left['CashierCode']] <=> [$right['PaymentMethod'], $right['CashierCode']]
        );

        $data['currency_allocations'] = $this->escapeValue($allocations);

        return $data;
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
