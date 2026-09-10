<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\HousekeepingInvoiceDataAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HousekeepingInvoiceDataAdapterTest extends TestCase
{
    public function test_adapts_la_br_and_mb_without_changing_supplied_values(): void
    {
        $adapter = new HousekeepingInvoiceDataAdapter();
        $row = [
            'BookingId' => 'BOOKING-REFERENCE', 'TotalAmount' => 120000,
            'DiscountAmount' => 10000, 'NetAmount' => 110000,
        ];

        foreach (['la', 'br', 'mb'] as $outlet) {
            $result = $adapter->adapt($outlet, [
                'parameters' => ['p_view_type' => 'All'],
                'rows' => [$row],
                'product_summary' => [['Product' => 'Synthetic product', 'Quantity' => 1, 'TotalAmount' => 110000]],
                'totals' => ['TotalAmount' => 120000, 'DiscountAmount' => 10000, 'NetAmount' => 110000],
            ]);

            $this->assertSame(strtoupper($outlet), $result['outlet']);
            $this->assertSame($row, $result['rows'][0]);
            $this->assertSame(110000, $result['totals']['NetAmount']);
        }
    }

    public function test_empty_result_has_the_runtime_dataset_shape(): void
    {
        $result = (new HousekeepingInvoiceDataAdapter())->empty('MB', ['p_from_date' => '2026-09-01']);

        $this->assertSame('MB', $result['outlet']);
        $this->assertSame(['p_from_date' => '2026-09-01'], $result['parameters']);
        $this->assertSame([], $result['rows']);
        $this->assertSame([], $result['product_summary']);
        $this->assertSame([], $result['totals']);
    }

    public function test_expands_procedure_product_payload_and_removes_technical_fields(): void
    {
        $result = (new HousekeepingInvoiceDataAdapter())->adapt('LA', [
            'hotel' => ['name' => 'Hotel'],
            'fields' => [
                ['name' => 'BookingId'],
                ['name' => 'ProductItems'],
                ['name' => 'ReportTotalAmount'],
            ],
            'rows' => [
                [
                    'BookingId' => '<b>{{hotel.logo}}</b>',
                    'ProductItems' => json_encode([
                        ['Product' => 'Áo', 'Quantity' => 2, 'TotalAmount' => 40000],
                    ], JSON_UNESCAPED_UNICODE),
                    'ReportTotalAmount' => '50000.000000',
                    'ReportDiscountAmount' => '5000.000000',
                    'ReportNetAmount' => '45000.000000',
                ],
                [
                    'BookingId' => 'B2',
                    'ProductItems' => [['Product' => 'Áo', 'Quantity' => 1, 'TotalAmount' => 20000]],
                    'ReportTotalAmount' => '50000.000000',
                    'ReportDiscountAmount' => '5000.000000',
                    'ReportNetAmount' => '45000.000000',
                ],
            ],
        ]);

        $this->assertSame(['name' => 'Hotel'], $result['hotel']);
        $this->assertSame([['name' => 'BookingId']], $result['fields']);
        $this->assertArrayNotHasKey('ProductItems', $result['rows'][0]);
        $this->assertSame('&lt;b&gt;&#123;&#123;hotel.logo&#125;&#125;&lt;/b&gt;', $result['rows'][0]['BookingId']);
        $this->assertSame(3.0, $result['product_summary'][0]['Quantity']);
        $this->assertSame(60000.0, $result['product_summary'][0]['TotalAmount']);
        $this->assertSame('45000.000000', $result['totals']['NetAmount']);
    }

    public function test_rejects_unknown_outlet_and_non_array_dataset_parts(): void
    {
        $adapter = new HousekeepingInvoiceDataAdapter();

        $this->expectException(InvalidArgumentException::class);
        $adapter->adapt('FO', []);
    }

    public function test_rejects_non_array_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HousekeepingInvoiceDataAdapter())->adapt('LA', ['rows' => 'not-a-result-set']);
    }
}
