<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\CompanyDebtDataAdapter;
use PHPUnit\Framework\TestCase;

class CompanyDebtDataAdapterTest extends TestCase
{
    public function test_builds_company_and_settlement_bands_from_procedure_rows(): void
    {
        $result = (new CompanyDebtDataAdapter())->adapt([
            'fields' => [['name' => 'CompanyName'], ['name' => 'PaidAmount'], ['name' => 'SettlementItems']],
            'rows' => [
                [
                    'BookingId' => 'B1', 'CompanyId' => 10, 'CompanyName' => '<b>Công ty A</b>',
                    'PaymentId' => 20, 'TotalAmount' => 100, 'PaidAmount' => 40,
                    'RemainingAmount' => 60,
                    'SettlementItems' => json_encode([[
                        'SettlementId' => 30, 'Date' => '01/09/2026',
                        'Reference' => 'Thu AC', 'Amount' => 40,
                    ]], JSON_UNESCAPED_UNICODE),
                ],
            ],
        ]);

        $this->assertSame([['name' => 'CompanyName']], $result['fields']);
        $this->assertArrayNotHasKey('PaidAmount', $result['rows'][0]);
        $this->assertSame('&lt;b&gt;Công ty A&lt;/b&gt;', $result['rows'][0]['CompanyName']);
        $this->assertSame(100.0, $result['company_summary'][0]['TotalAmount']);
        $this->assertSame(40.0, $result['company_summary'][0]['PaidAmount']);
        $this->assertSame(60.0, $result['totals']['RemainingAmount']);
        $this->assertSame(30, $result['settlement_details'][0]['SettlementId']);
        $this->assertSame('B1', $result['settlement_details'][0]['BookingId']);
    }

    public function test_deduplicates_a_settlement_repeated_by_legacy_row_grain(): void
    {
        $item = [['SettlementId' => 5, 'Date' => '01/09/2026', 'Amount' => 25]];
        $result = (new CompanyDebtDataAdapter())->adapt(['rows' => [
            ['BookingId' => 'B1', 'CompanyName' => 'A', 'PaymentId' => 9, 'SettlementItems' => $item],
            ['BookingId' => 'B1', 'CompanyName' => 'A', 'PaymentId' => 9, 'SettlementItems' => $item],
        ]]);

        $this->assertCount(1, $result['settlement_details']);
    }
}
