<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Builds presentation-only group labels and payment summaries from the
 * single result set returned by the sp_039-compatible procedure.
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
        $data['city_ledger_rows'] = $this->cityLedgerRows($data['parameters'] ?? []);

        return $data;
    }

    private function cityLedgerRows(array $parameters): array
    {
        $settlements = DB::table('payment_debt_settlements')
            ->select('payment_id')
            ->selectRaw('SUM(CASE WHEN COALESCE(edit_flag, 0) = 0 AND deleted_at IS NULL THEN amount ELSE 0 END) AS paid_amount')
            ->groupBy('payment_id');

        $query = DB::table('payments as p')
            ->leftJoin('payment_methods as pm', 'pm.code', '=', 'p.payment_method_id')
            ->leftJoin('bookings as b', 'b.id', '=', 'p.booking_id')
            ->leftJoinSub($settlements, 'settled', 'settled.payment_id', '=', 'p.id')
            ->whereBetween('p.date', [
                $parameters['p_from_date'] ?? now()->toDateString(),
                $parameters['p_to_date'] ?? now()->toDateString(),
            ])
            ->where(function ($nested) {
                $nested->where('pm.code', 'AC')->orWhere('p.payment_method_id', 'AC');
            })
            ->where(function ($nested) {
                $nested->where('p.edit_flag', 0)->orWhereNull('p.edit_flag');
            })
            ->whereNull('p.deleted_at');

        $department = trim((string) ($parameters['p_department'] ?? ''));
        if ($department !== '') {
            $query->whereRaw(
                "FIND_IN_SET(COALESCE(NULLIF(p.department_id, ''), ''), ?) > 0",
                [str_replace(' ', '', $department)]
            );
        }

        $user = trim((string) ($parameters['p_user'] ?? ''));
        if ($user !== '') {
            $query->whereRaw(
                "FIND_IN_SET(COALESCE(NULLIF(p.created_by, ''), p.username), ?) > 0",
                [str_replace(' ', '', $user)]
            );
        }

        $shift = trim((string) ($parameters['p_shift'] ?? ''));
        if ($shift !== '') {
            $query->where('p.shift', $shift);
        }

        $companyId = trim((string) ($parameters['p_company_id'] ?? ''));
        if ($companyId !== '' && ! in_array($companyId, ['-1', '0'], true)) {
            $query->whereRaw('COALESCE(p.company_id, b.company_id) = ?', [$companyId]);
        }

        $paymentMethod = trim((string) ($parameters['p_payment_method'] ?? ''));
        if ($paymentMethod !== '') {
            $query->whereRaw(
                "FIND_IN_SET(COALESCE(pm.code, p.payment_method_id), ?) > 0",
                [str_replace(' ', '', $paymentMethod)]
            );
        }

        $fromTime = trim((string) ($parameters['p_from_time'] ?? ''));
        if ($fromTime !== '') {
            $query->whereRaw("TIME(COALESCE(p.open_time, TIME(p.created_at))) >= ?", [$fromTime]);
        }

        $toTime = trim((string) ($parameters['p_to_time'] ?? ''));
        if ($toTime !== '') {
            $query->whereRaw("TIME(COALESCE(p.open_time, TIME(p.created_at))) <= ?", [$toTime]);
        }

        if (! (bool) ($parameters['p_view_deposit'] ?? true)) {
            $query->where(function ($nested) {
                $nested->whereNull('p.pack2')->orWhere('p.pack2', '<>', 'DPR');
            });
        }

        if (! (bool) ($parameters['p_view_amount_zero'] ?? false)) {
            $query->where('p.amount', '<>', 0);
        }

        $summary = $query
            ->selectRaw('COALESCE(SUM(p.amount), 0) AS total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(settled.paid_amount, 0)), 0) AS paid_amount')
            ->first();

        if (! $summary || ((float) $summary->total_amount === 0.0 && (float) $summary->paid_amount === 0.0)) {
            return [];
        }

        return [[
            'PaymentMethodTitle' => 'AC (City ledger/Công nợ)',
            'TotalAmount' => (float) $summary->total_amount,
            'PaidAmount' => (float) $summary->paid_amount,
            'BalanceAmount' => (float) $summary->total_amount - (float) $summary->paid_amount,
        ]];
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
