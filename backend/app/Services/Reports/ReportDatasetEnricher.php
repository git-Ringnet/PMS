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
    public function __construct(
        private readonly ArrivingRoomsSummaryService $arrivingRoomsSummary,
        private readonly HousekeepingInvoiceDataAdapter $housekeepingInvoices,
        private readonly CompanyDebtDataAdapter $companyDebt,
    ) {}

    public function enrich(ReportDefinition $reportDefinition, array $data): array
    {
        $code = $this->reportCode($reportDefinition);
        $outlet = match ($code) {
            'LAUNDRY_INVOICES' => 'LA',
            'BREAKAGE_INVOICES' => 'BR',
            'MINIBAR_INVOICES' => 'MB',
            default => null,
        };
        if ($outlet !== null) {
            return $this->housekeepingInvoices->adapt($outlet, $data);
        }
        if ($code === 'COMPANY_DEBT') {
            return $this->companyDebt->adapt($this->resolveSystemUserNames($data));
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
}
