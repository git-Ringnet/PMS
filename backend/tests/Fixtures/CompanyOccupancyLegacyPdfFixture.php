<?php

namespace Tests\Fixtures;

final class CompanyOccupancyLegacyPdfFixture
{
    public static function summary(): array
    {
        return [
            'parameters' => ['p_from_date' => '2026-06-11', 'p_to_date' => '2026-06-11'],
            'rows' => [
                [
                    'CompanyCode' => 'CTY001', 'CompanyName' => 'Công ty A', 'TotalRevenue' => 41250000,
                    'RevenuePercentage' => 57.18, 'AverageRevenue' => 1250000, 'RoomQty' => 18, 'RoomNight' => 33,
                    'FocRoomNights' => 2, 'HuRoomNights' => 1, 'OccupancyRate' => 35.48, 'GuestQty' => 64,
                    'GuestNight' => 64, 'RoomRevenue' => 40000000, 'ExtraBedRevenue' => 1000000, 'ChildSurchargeRevenue' => 250000,
                    'RoomAvailable' => 93,
                ],
                [
                    'CompanyCode' => 'CTY002', 'CompanyName' => 'Công ty B', 'TotalRevenue' => 30950000,
                    'RevenuePercentage' => 42.82, 'AverageRevenue' => 1105357, 'RoomQty' => 11, 'RoomNight' => 24,
                    'FocRoomNights' => 1, 'HuRoomNights' => 1, 'OccupancyRate' => 25.81, 'GuestQty' => 48,
                    'GuestNight' => 48, 'RoomRevenue' => 30000000, 'ExtraBedRevenue' => 500000, 'ChildSurchargeRevenue' => 150000,
                    'RoomAvailable' => 93,
                ],
            ],
        ];
    }

    public static function detail(): array
    {
        return [
            'parameters' => ['p_from_date' => '2026-06-11', 'p_to_date' => '2026-06-11'],
            'rows' => [
                [
                    'CompanyCode' => 'CTY001', 'CompanyName' => 'Công ty A', 'RowNumber' => 1, 'BookingCode' => 'BK001',
                    'GuestName' => 'Nguyễn Văn A', 'ArrivalDate' => '11/06/2026', 'DepartureDate' => '12/06/2026',
                    'RoomNight' => 2, 'GuestNight' => 4, 'RoomRevenue' => 2400000, 'ExtraBedRevenue' => 100000,
                    'ChildSurchargeRevenue' => 50000, 'TotalRevenue' => 2550000,
                ],
                [
                    'CompanyCode' => 'CTY002', 'CompanyName' => 'Công ty B', 'RowNumber' => 1, 'BookingCode' => 'BK002',
                    'GuestName' => 'Trần Thị B', 'ArrivalDate' => '11/06/2026', 'DepartureDate' => '13/06/2026',
                    'RoomNight' => 3, 'GuestNight' => 6, 'RoomRevenue' => 3600000, 'ExtraBedRevenue' => 0,
                    'ChildSurchargeRevenue' => 100000, 'TotalRevenue' => 3700000,
                ],
            ],
        ];
    }
}
