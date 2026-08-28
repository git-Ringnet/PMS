<?php

namespace App\Services\Reports;

use App\Models\ReportDefinition;

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
    ) {}

    public function enrich(ReportDefinition $reportDefinition, array $data): array
    {
        if (isset($data['room_type_summary'])) {
            return $data;
        }

        $summary = match ($this->reportCode($reportDefinition)) {
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
}
