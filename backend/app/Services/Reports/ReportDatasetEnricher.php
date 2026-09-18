<?php

namespace App\Services\Reports;

use App\Models\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Optional presentation data derived after a Store returns its rows.
 *
 * ReportDefinitionController stays transport-only. A future report that needs
 * calculated data belongs here (or in a dedicated enricher), not in the API
 * controller shared by every report.
 */
class ReportDatasetEnricher
{
    private readonly PaidCompanyDebtsDataAdapter $paidCompanyDebts;
    private readonly SalesInvoicesDataAdapter $salesInvoices;

    public function __construct(
        private readonly ArrivingRoomsSummaryService $arrivingRoomsSummary,
        private readonly HousekeepingInvoiceDataAdapter $housekeepingInvoices,
        private readonly CompanyDebtDataAdapter $companyDebt,
        ?PaidCompanyDebtsDataAdapter $paidCompanyDebts = null,
        ?SalesInvoicesDataAdapter $salesInvoices = null,
    ) {
        $this->paidCompanyDebts = $paidCompanyDebts ?? new PaidCompanyDebtsDataAdapter();
        $this->salesInvoices = $salesInvoices ?? new SalesInvoicesDataAdapter();
    }

    public function enrich(ReportDefinition $reportDefinition, array $data): array
    {
        $code = $this->reportCode($reportDefinition);
        $outlet = match ($code) {
            'LAUNDRY_INVOICES' => 'LA',
            'LAUNDRY_FREE_INVOICES' => 'LA',
            'BREAKAGE_FREE_INVOICES' => 'BR',
            'BREAKAGE_INVOICES' => 'BR',
            'MINIBAR_INVOICES' => 'MB',
            'MINIBAR_FREE_INVOICES' => 'MB',
            default => null,
        };
        if ($outlet !== null) {
            return $this->housekeepingInvoices->adapt($outlet, $data);
        }
        if ($code === 'COMPANY_DEBT') {
            return $this->companyDebt->adapt($this->resolveSystemUserNames($data));
        }
        if ($code === 'PAID_COMPANY_DEBTS') {
            return $this->paidCompanyDebts->adapt($this->resolvePaidDebtUserNames($data));
        }
        if (in_array($code, ['SALES_INVOICES', 'RPT_SALES_INVOICES'], true)) {
            return $this->salesInvoices->adapt($this->resolveSystemUserNames($data));
        }
        if (in_array($code, ['EXPECTED_BREAKFAST', 'EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'], true)) {
            return $this->enrichExpectedBreakfast($data);
        }

        if (isset($data['room_type_summary'])) {
            return $data;
        }

        $summary = match ($code) {
            'ARRIVING_ROOMS' => $this->arrivingRoomsSummary->summarize($data['rows'] ?? []),
            default => null,
        };

        if ($summary === null) {
            return $data;
        }

        $data['room_type_summary'] = $summary['rows'];
        $data['room_type_summary_total'] = $summary['total'];

        return $data;
    }

    private function reportCode(ReportDefinition $reportDefinition): string
    {
        return strtoupper((string) ($reportDefinition->reportDataSource?->code ?: $reportDefinition->code));
    }

    private function resolveSystemUserNames(array $data): array
    {
        $usernames = collect($data['rows'] ?? [])->pluck('Username')->filter()->unique()->values();
        if ($usernames->isEmpty()) {
            return $data;
        }

        $connection = config('database_domains.system_connection', 'mysql_system');
        $names = DB::connection($connection)->table('users')
            ->whereIn('username', $usernames)
            ->pluck('name', 'username');

        foreach ($data['rows'] as &$row) {
            $username = (string) ($row['Username'] ?? '');
            $row['Username'] = $names[$username] ?? $username;
        }
        unset($row);

        return $data;
    }

    private function resolvePaidDebtUserNames(array $data): array
    {
        $rows = $data['rows'] ?? [];
        if (empty($rows)) {
            return $data;
        }

        $usernames = collect($rows)
            ->flatMap(fn (array $r) => [$r['UserCN'] ?? '', $r['UserTT'] ?? '', $r['Username'] ?? ''])
            ->filter()
            ->unique()
            ->values();

        if ($usernames->isEmpty()) {
            return $data;
        }

        $connection = config('database_domains.system_connection', 'mysql_system');
        $names = DB::connection($connection)->table('users')
            ->whereIn('username', $usernames)
            ->pluck('name', 'username');

        foreach ($data['rows'] as &$row) {
            if (! empty($row['UserCN']) && isset($names[$row['UserCN']])) {
                $row['UserCN'] = $names[$row['UserCN']];
            }
            if (! empty($row['UserTT']) && isset($names[$row['UserTT']])) {
                $row['UserTT'] = $names[$row['UserTT']];
            }
            if (! empty($row['Username']) && isset($names[$row['Username']])) {
                $row['Username'] = $names[$row['Username']];
            }
        }
        unset($row);

        return $data;
    }

    private function enrichExpectedBreakfast(array $data): array
    {
        if (isset($data['country_summary'])) {
            return $data;
        }

        $rows = $data['rows'] ?? [];
        $countries = [];
        $totalPax = 0;

        foreach ($rows as $row) {
            $nationality = trim((string) ($row['Nationality'] ?? '')) ?: 'Không xác định';
            $pax = (int) ($row['TotalPax'] ?? 0);
            if ($pax <= 0) {
                $pax = (int) ($row['Adults'] ?? 0) + (int) ($row['Children'] ?? 0) + (int) ($row['ChildrenNK'] ?? 0);
                if ($pax <= 0) {
                    $pax = 1;
                }
            }
            if (!isset($countries[$nationality])) {
                $countries[$nationality] = 0;
            }
            $countries[$nationality] += $pax;
            $totalPax += $pax;
        }

        $summary = [];
        foreach ($countries as $nationality => $qty) {
            $percentage = $totalPax > 0 ? round(($qty / $totalPax) * 100, 2) : 0.00;
            $summary[] = [
                'Nationality' => $nationality,
                'Quantity' => $qty,
                'Percentage' => number_format($percentage, 2) . '%',
            ];
        }

        usort($summary, fn ($a, $b) => $b['Quantity'] <=> $a['Quantity']);

        $data['country_summary'] = $summary;
        $data['totals'] = array_merge($data['totals'] ?? [], [
            'CountryTotalPax' => $totalPax,
        ]);

        return $data;
    }
}
