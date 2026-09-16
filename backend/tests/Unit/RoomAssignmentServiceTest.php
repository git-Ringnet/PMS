<?php

namespace Tests\Unit;

use App\Models\Room;
use App\Services\RoomAssignmentService;
use PHPUnit\Framework\TestCase;

class RoomAssignmentServiceTest extends TestCase
{
    public function test_numeric_floors_are_sorted_numerically_and_room_numbers_naturally(): void
    {
        $rooms = collect([
            $this->room('1005', '10', 4),
            $this->room('205', '2', 3),
            $this->room('105', '1', 1),
            $this->room('106', '1', 2),
            $this->room('305', '3', 5),
            $this->room('206', '2', 6),
            $this->room('100', '1', 7),
        ]);

        $ordered = (new RoomAssignmentService)->sortCandidates($rooms);

        $this->assertSame(
            ['100', '105', '106', '205', '206', '305', '1005'],
            $ordered->pluck('room_number')->all(),
        );
    }

    public function test_symbolic_floors_are_not_coerced_to_numeric_floor_zero(): void
    {
        $rooms = collect([
            $this->room('G01', 'G', 1),
            $this->room('B101', 'B1', 2),
            $this->room('201', '2', 3),
            $this->room('101', '1', 4),
        ]);

        $ordered = (new RoomAssignmentService)->sortCandidates($rooms);

        $this->assertSame(['101', '201', 'B101', 'G01'], $ordered->pluck('room_number')->all());
    }

    private function room(string $roomNumber, string $floor, int $id): Room
    {
        $room = new Room([
            'room_number' => $roomNumber,
            'floor' => $floor,
        ]);
        $room->id = $id;

        return $room;
    }
}
