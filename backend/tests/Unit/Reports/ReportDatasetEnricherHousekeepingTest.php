<?php

namespace Tests\Unit\Reports;

use App\Models\ReportDefinition;
use App\Services\Reports\ArrivingRoomsSummaryService;
use App\Services\Reports\CompanyDebtDataAdapter;
use App\Services\Reports\HousekeepingInvoiceDataAdapter;
use App\Services\Reports\ReportDatasetEnricher;
use Tests\TestCase;

class ReportDatasetEnricherHousekeepingTest extends TestCase
{
    public function test_only_housekeeping_invoice_codes_use_the_housekeeping_adapter(): void
    {
        $enricher = new ReportDatasetEnricher(
            new ArrivingRoomsSummaryService(),
            new HousekeepingInvoiceDataAdapter(),
            new CompanyDebtDataAdapter(),
        );

        foreach ([
            'LAUNDRY_INVOICES' => 'LA',
            'BREAKAGE_INVOICES' => 'BR',
            'MINIBAR_INVOICES' => 'MB',
        ] as $code => $outlet) {
            $report = new ReportDefinition(['code' => $code]);
            $data = $enricher->enrich($report, ['rows' => [], 'hotel' => ['name' => 'Hotel']]);

            $this->assertSame($outlet, $data['outlet']);
            $this->assertSame(['name' => 'Hotel'], $data['hotel']);
        }

        $ordinary = $enricher->enrich(new ReportDefinition(['code' => 'OTHER']), ['rows' => []]);
        $this->assertArrayNotHasKey('outlet', $ordinary);

        $debt = $enricher->enrich(new ReportDefinition(['code' => 'COMPANY_DEBT']), [
            'rows' => [['CompanyName' => 'A', 'TotalAmount' => 10, 'PaidAmount' => 4, 'RemainingAmount' => 6]],
        ]);
        $this->assertSame(6.0, $debt['totals']['RemainingAmount']);
    }
}
