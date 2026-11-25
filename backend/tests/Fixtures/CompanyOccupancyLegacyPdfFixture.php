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
                    'ReferenceCode' => 'REF001', 'GuestName' => 'Nguyễn Văn A', 'MarketSegment' => 'QT', 'SourceCode' => 'TA',
                    'BookingDate' => '10/06/2026', 'ArrivalDate' => '11/06/2026', 'DepartureDate' => '12/06/2026',
                    'NoOfNight' => 1, 'NoOfRoom' => 1, 'RoomNight' => 2, 'GuestNight' => 4,
                    'AverageRate' => 1200000, 'AverageRateOriginal' => 1200000, 'RoomRevenue' => 2400000,
                    'FbRevenue' => 0, 'OtherRevenue' => 150000, 'TotalRevenue' => 2550000,
                    'RoomType' => 'STD', 'Nationality' => 'VNM',
                ],
                [
                    'CompanyCode' => 'CTY002', 'CompanyName' => 'Công ty B', 'RowNumber' => 1, 'BookingCode' => 'BK002',
                    'ReferenceCode' => 'REF002', 'GuestName' => 'Trần Thị B', 'MarketSegment' => 'ND', 'SourceCode' => 'TA',
                    'BookingDate' => '09/06/2026', 'ArrivalDate' => '11/06/2026', 'DepartureDate' => '13/06/2026',
                    'NoOfNight' => 2, 'NoOfRoom' => 1, 'RoomNight' => 3, 'GuestNight' => 6,
                    'AverageRate' => 1200000, 'AverageRateOriginal' => 1200000, 'RoomRevenue' => 3600000,
                    'FbRevenue' => 0, 'OtherRevenue' => 100000, 'TotalRevenue' => 3700000,
                    'RoomType' => 'DLX', 'Nationality' => 'VNM',
                ],
            ],
            'aggregate' => [
                'rows' => [
                    'count' => 2,
                    'sum' => [
                        'NoOfNight' => 3,
                        'NoOfRoom' => 2,
                        'RoomNight' => 5,
                        'GuestNight' => 10,
                        'RoomRevenue' => 6000000,
                        'FbRevenue' => 0,
                        'OtherRevenue' => 250000,
                        'TotalRevenue' => 6250000,
                    ],
                ],
            ],
        ];
    }
}
