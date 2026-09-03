<?php

namespace Tests\Unit;

use App\Services\Reports\ArrivingRoomsSummaryService;
use Tests\TestCase;

class ArrivingRoomsSummaryServiceTest extends TestCase
{
    public function test_it_builds_room_type_summary_once_per_room(): void
    {
        $summary = app(ArrivingRoomsSummaryService::class)->summarize([
            ['RentalRoomId' => 'R1', 'RoomTypeCode' => 'SUPD', 'RoomNight' => 1, 'Adult' => 2, 'Child' => 0, 'IsMainGuest' => 1],
            ['RentalRoomId' => 'R1', 'RoomTypeCode' => 'SUPD', 'RoomNight' => null, 'Adult' => null, 'Child' => null, 'IsMainGuest' => 0],
            ['RentalRoomId' => 'R2', 'RoomTypeCode' => 'SUPD', 'RoomNight' => 2, 'Adult' => 1, 'Child' => 1, 'IsMainGuest' => 1],
            ['RentalRoomId' => 'R3', 'RoomTypeCode' => 'SUPT', 'RoomNight' => 1, 'Adult' => 2, 'Child' => 0, 'IsMainGuest' => 1],
        ]);

        $this->assertSame([
            ['room_type_code' => 'SUPD', 'qty' => 2, 'nights' => 3, 'guests' => '3 / 1', 'percentage' => '66.67%'],
            ['room_type_code' => 'SUPT', 'qty' => 1, 'nights' => 1, 'guests' => '2 / 0', 'percentage' => '33.33%'],
        ], $summary['rows']);
        $this->assertSame(['qty' => 3, 'nights' => 4, 'guests' => '5 / 1', 'percentage' => '100%'], $summary['total']);
    }
}
