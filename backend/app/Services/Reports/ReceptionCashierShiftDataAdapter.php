<?php

namespace App\Services\Reports;

/**
 * Builds presentation-only group labels and a provisional payment allocation
 * summary from the single result set returned by legacy sp_039.
 *
 * This adapter is intentionally not registered with ReportDatasetEnricher.
 * It does not create a city-ledger dataset.
 */
final class ReceptionCashierShiftDataAdapter
{
    /**
     * @param array<string, mixed> $data Report dataset with the sp_039-shaped rows list.
     * @return array<string, mixed>
     */
    public function adapt(array $data): array
    {
        $sourceRows = $data['rows'] ?? [];
        if (! is_array($sourceRows)) {
            return $data;
        }

        $groups = [];
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            if (! is_array($sourceRow) && ! is_object($sourceRow)) {
                continue;
            }

            $row = (array) $sourceRow;
            $methodCode = strtoupper(trim((string) ($row['PaymentMethod'] ?? '')));
            $methodName = trim((string) ($row['PaymentMethodName'] ?? ''));
            $depositCode = (string) ($row['Deposit'] ?? '');
            $amount = is_numeric($row['Amount'] ?? null) ? (float) $row['Amount'] : 0.0;
            $groupKey = serialize([$methodCode, $methodName]);

            $row['PaymentMethodGroup'] = $methodCode !== '' ? $methodCode : '__UNKNOWN__';
            $row['PaymentMethodGroupTitle'] = $this->escapeLabel($this->methodTitle($methodCode, $methodName));
            $row['DepositGroupTitle'] = $this->escapeLabel((string) ($row['ShowDeposit'] ?? ''));
            $rows[] = $row;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'PaymentMethod' => $methodCode,
                    'PaymentMethodName' => $methodName,
                    'PaymentMethodTitle' => $this->escapeLabel($this->methodTitle($methodCode, $methodName)),
                    'CashierAmount' => 0.0,
                    'DepositAmount' => 0.0,
                    'TotalCashierDeposit' => 0.0,
                    'RefundAmount' => 0.0,
                    'NetTotal' => 0.0,
                ];
            }

            // Deposit is produced by sp_039: 0=cashier, 1=deposit, 2=refund.
            // Amount sign is authoritative for refund amount and net total.
            if ($amount < 0) {
                $groups[$groupKey]['RefundAmount'] += abs($amount);
            } elseif ($depositCode === '0') {
                $groups[$groupKey]['CashierAmount'] += $amount;
            } elseif ($depositCode === '1') {
                $groups[$groupKey]['DepositAmount'] += $amount;
            }

            $groups[$groupKey]['NetTotal'] += $amount;
        }

        foreach ($groups as &$group) {
            $group['TotalCashierDeposit'] = $group['CashierAmount'] + $group['DepositAmount'];
        }
        unset($group);

        $allocations = array_values($groups);
        usort($allocations, static fn (array $left, array $right): int =>
            [$left['PaymentMethod'], $left['PaymentMethodName']] <=> [$right['PaymentMethod'], $right['PaymentMethodName']]
        );

        $data['rows'] = $rows;
        $data['currency_allocations'] = $allocations;

        return $data;
    }

    private function methodTitle(string $code, string $name): string
    {
        if ($code !== '' && $name !== '') {
            return $code.' - '.$name;
        }

        return $code !== '' ? $code : ($name !== '' ? $name : 'Không xác định');
    }

    private function escapeLabel(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return strtr($escaped, ['{' => '&#123;', '}' => '&#125;']);
    }
}
