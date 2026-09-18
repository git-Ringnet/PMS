<?php

namespace App\Services\Reports;

use InvalidArgumentException;

/**
 * Adapts and enriches raw query results for the SALES_INVOICES report layout.
 * Builds formatted rows, daily grouping, and the secondary currency allocation table.
 */
final class SalesInvoicesDataAdapter
{
    public function adapt(array $result): array
    {
        foreach (['rows', 'currency_allocations'] as $key) {
            if (array_key_exists($key, $result) && ! is_array($result[$key])) {
                throw new InvalidArgumentException("Sales invoices {$key} must be an array.");
            }
        }

        $rawRows = array_values($result['rows'] ?? []);
        $processedRows = [];

        foreach ($rawRows as $row) {
            $processed = (array) $row;

            // 1. Format date (dd/mm/yyyy)
            $dateVal = $processed['InvoiceDateFormatted'] ?? $processed['Date'] ?? $processed['InvoiceDate'] ?? '';
            if ($dateVal && ! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateVal)) {
                $time = strtotime($dateVal);
                if ($time !== false) {
                    $dateVal = date('d/m/Y', $time);
                }
            }
            $processed['InvoiceDateFormatted'] = $dateVal ?: date('d/m/Y');

            // 2. Numeric amounts
            $amount = (float) ($processed['Amount'] ?? $processed['DoanhThu'] ?? 0);
            $cash = (float) ($processed['Cash'] ?? 0);
            $card = (float) ($processed['Card'] ?? 0);
            $voucher = (float) ($processed['Voucher'] ?? 0);
            $city = (float) ($processed['City'] ?? 0);
            $dpCash = (float) ($processed['DPCash'] ?? 0);
            $dpCard = (float) ($processed['DPCard'] ?? 0);

            // Defensive check: If payments were not joined but method is present
            $totalSplit = $cash + $card + $voucher + $city + $dpCash + $dpCard;
            if ($totalSplit == 0 && $amount != 0) {
                $method = strtoupper((string) ($processed['PaymentMethod'] ?? ''));
                if (str_contains($method, 'CA') || str_contains($method, 'TIỀN MẶT')) {
                    $cash = $amount;
                } elseif (str_contains($method, 'BT') || str_contains($method, 'CD') || str_contains($method, 'THẺ') || str_contains($method, 'CK')) {
                    $card = $amount;
                } elseif (str_contains($method, 'VO') || str_contains($method, 'VOUCHER')) {
                    $voucher = $amount;
                } elseif (str_contains($method, 'AC') || str_contains($method, 'CITY') || str_contains($method, 'CÔNG NỢ')) {
                    $city = $amount;
                } else {
                    $cash = $amount;
                }
            }

            $processed['Amount'] = $amount;
            $processed['Cash'] = $cash;
            $processed['Card'] = $card;
            $processed['Voucher'] = $voucher;
            $processed['City'] = $city;
            $processed['DPCash'] = $dpCash;
            $processed['DPCard'] = $dpCard;

            // 3. String fields
            $processed['BillID'] = (string) ($processed['BillID'] ?? $processed['Ma'] ?? '');
            $processed['PaymentID'] = (string) ($processed['PaymentID'] ?? '');
            $processed['Room'] = (string) ($processed['Room'] ?? '');
            $processed['VATNo'] = (string) ($processed['VATNo'] ?? '');
            $processed['GuestName'] = (string) ($processed['GuestName'] ?? '');
            $processed['CompanyName'] = (string) ($processed['CompanyName'] ?? '');

            $processedRows[] = $processed;
        }

        // 4. Build currency allocations table (3 categories matching legacy)
        $allocations = $result['currency_allocations'] ?? $this->buildCurrencyAllocations($processedRows);

        $result['rows'] = $this->escapeValue($processedRows);
        $result['currency_allocations'] = $this->escapeValue($allocations);

        return $result;
    }

    private function buildCurrencyAllocations(array $rows): array
    {
        $bankCashier = 0.0;
        $bankDeposit = 0.0;
        $cashCashier = 0.0;
        $cashDeposit = 0.0;
        $cardCashier = 0.0;
        $cardDeposit = 0.0;

        foreach ($rows as $row) {
            $method = strtoupper((string) ($row['PaymentMethod'] ?? ''));
            $c = (float) ($row['Cash'] ?? 0);
            $dpc = (float) ($row['DPCash'] ?? 0);
            $card = (float) ($row['Card'] ?? 0);
            $dpCard = (float) ($row['DPCard'] ?? 0);

            // Cash
            $cashCashier += $c;
            $cashDeposit += $dpc;

            // Card / Bank Transfer split
            if (str_contains($method, 'BT') || str_contains($method, 'BANK') || str_contains($method, 'CHUYỂN KHOẢN')) {
                $bankCashier += $card;
                $bankDeposit += $dpCard;
            } else {
                $cardCashier += $card;
                $cardDeposit += $dpCard;
            }
        }

        return [
            [
                'Method' => 'Bank transfer/ Chuyển khoản',
                'CashierAmount' => $bankCashier,
                'DepositAmount' => $bankDeposit,
                'TotalAmount' => $bankCashier + $bankDeposit,
            ],
            [
                'Method' => 'Cash/ Tiền mặt',
                'CashierAmount' => $cashCashier,
                'DepositAmount' => $cashDeposit,
                'TotalAmount' => $cashCashier + $cashDeposit,
            ],
            [
                'Method' => 'Credit Card/ Cà thẻ',
                'CashierAmount' => $cardCashier,
                'DepositAmount' => $cardDeposit,
                'TotalAmount' => $cardCashier + $cardDeposit,
            ],
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
