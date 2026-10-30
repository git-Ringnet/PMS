<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\CompanyOccupancyDataAdapter;
use PHPUnit\Framework\TestCase;

class CompanyOccupancyDataAdapterTest extends TestCase
{
    public function test_computes_occupancy_rates_and_adrs_correctly(): void
    {
        $adapter = new CompanyOccupancyDataAdapter();
        $result = $adapter->adapt([
            'rows' => [
                [
                    'CompanyCode' => 'COMP01',
                    'CompanyName' => 'Cong ty A',
                    'TotalRoomAvailable' => 200,
                    'RoomNight' => 50,
                    'RoomRevenue' => 50000000,
                    'RackRevenue' => 60000000,
                    'Foc' => 5,
                    'Hu' => 5,
                ],
                [
                    'CompanyCode' => 'COMP02',
                    'CompanyName' => 'Cong ty B',
                    'TotalRoomAvailable' => 200,
                    'RoomNight' => 30,
                    'RoomRevenue' => 30000000,
                    'RackRevenue' => 35000000,
                    'Foc' => 0,
                    'Hu' => 0,
                ],
            ],
            'parameters' => [
                'p_group_by' => 'COMPANY',
            ],
            'aggregate' => [
                'rows' => [],
            ],
        ]);

        // Total room nights: 80 / 200 = 40.0%
        $this->assertSame(40.0, $result['aggregate']['rows']['summary_occupancy']);
        // Actual ADR: 80,000,000 / 80 = 1,000,000
        $this->assertEquals(1000000, $result['aggregate']['rows']['summary_actual_adr']);
        // Paid nights: 80 - 10 = 70. Rack ADR: 80,000,000 / 70 = 1,142,857
        $this->assertEquals(1142857, $result['aggregate']['rows']['summary_rack_adr']);
        // Not grouped when p_group_by is COMPANY
        $this->assertSame('0', $result['parameters']['p_is_grouped']);
    }

    public function test_sets_grouped_flag_when_group_by_is_not_none_or_company(): void
    {
        $adapter = new CompanyOccupancyDataAdapter();
        $result = $adapter->adapt([
            'rows' => [
                [
                    'CompanyCode' => 'COMP01',
                    'TotalRoomAvailable' => 100,
                    'RoomNight' => 10,
                    'RoomRevenue' => 10000000,
                    'RackRevenue' => 10000000,
                    'Foc' => 0,
                    'Hu' => 0,
                ],
            ],
            'parameters' => [
                'p_group_by' => 'DAY_MARKET',
            ],
            'aggregate' => [
                'rows' => [],
            ],
        ]);

        $this->assertSame('1', $result['parameters']['p_is_grouped']);
        $this->assertSame(10.0, $result['aggregate']['rows']['summary_occupancy']);
    }

    public function test_handles_empty_rows_safely(): void
    {
        $adapter = new CompanyOccupancyDataAdapter();
        $result = $adapter->adapt([
            'rows' => [],
            'parameters' => [
                'p_group_by' => 'COMPANY',
            ],
            'aggregate' => [
                'rows' => [],
            ],
        ]);

        $this->assertSame(0, $result['aggregate']['rows']['summary_occupancy']);
        $this->assertSame(0, $result['aggregate']['rows']['summary_actual_adr']);
        $this->assertSame(0, $result['aggregate']['rows']['summary_rack_adr']);
        $this->assertSame('0', $result['parameters']['p_is_grouped']);
    }
}

