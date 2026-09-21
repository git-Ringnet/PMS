<?php

namespace App\Services\Reports;

use InvalidArgumentException;

/**
 * Builds secondary bands, payment method summary, and totals
 * for the PAID_COMPANY_DEBTS report layout.
 */
final class PaidCompanyDebtsDataAdapter
{
    public function adapt(array $result): array
    {
        foreach (['rows', 'payment_method_summary', 'totals'] as $key) {
            if (array_key_exists($key, $result) && ! is_array($result[$key])) {
                throw new InvalidArgumentException("Paid company debt {$key} must be an array.");
            }
        }

        $rows = array_values($result['rows'] ?? []);
        $methodSummary = $result['payment_method_summary'] ?? $this->methodSummary($rows);
        $totals = $result['totals'] ?? $this->totals($rows);

        $result['rows'] = $this->escapeValue($rows);
        $result['payment_method_summary'] = $this->escapeValue(array_values($methodSummary));
        $result['totals'] = $totals;

        return $result;
    }

    private function methodSummary(array $rows): array
    {
        $methods = [];
        foreach ($rows as $row) {
            $method = trim((string) ($row['PaymentMethodTT'] ?? $row['SettlementMethod'] ?? ''));
            if ($method === '') {
                $method = 'Khác';
            }
            $amount = (float) ($row['AmountTT'] ?? $row['PaidAmount'] ?? 0);

            $methods[$method] ??= [
                'Method' => $method,
                'Amount' => 0.0,
            ];
            $methods[$method]['Amount'] += $amount;
        }

        uasort($methods, static fn (array $a, array $b): int => strnatcasecmp(
            (string) $a['Method'],
            (string) $b['Method']
        ));

        return array_values($methods);
    }

    private function totals(array $rows): array
    {
        $uniqueDebts = [];
        $totalPaid = 0.0;

        foreach ($rows as $row) {
            $debtKey = (string) ($row['DebtRowKey'] ?? $row['PaymentId'] ?? '');
            $debtAmount = (float) ($row['AmountCN'] ?? $row['DebtAmount'] ?? 0);
            if ($debtKey !== '') {
                $uniqueDebts[$debtKey] = $debtAmount;
            }

            $totalPaid += (float) ($row['AmountTT'] ?? $row['PaidAmount'] ?? 0);
        }

        $totalDebt = array_sum($uniqueDebts);
        $totalRemaining = max(0.0, $totalDebt - $totalPaid);

        return [
            'DebtAmount' => $totalDebt,
            'PaidAmount' => $totalPaid,
            'RemainingAmount' => $totalRemaining,
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
