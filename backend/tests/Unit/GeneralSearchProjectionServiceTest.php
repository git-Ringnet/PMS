<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Services\GeneralSearchProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GeneralSearchProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_projection_keeps_the_parent_booking_reference(): void
    {
        $booking = new Booking(['booking_name' => 'Booking test']);
        $booking->id = 42;

        $room = new BookingRoom([
            'booking_id' => $booking->id,
            'room_number' => '101',
            'status' => BookingRoom::STATUS_BOOKED,
            'arrival_date' => '2026-09-18',
            'departure_date' => '2026-09-19',
        ]);
        $room->id = 'G0000042';
        $room->setRelation('guests', new Collection());
        $booking->setRelation('bookingRooms', collect([$room]));

        $row = app(GeneralSearchProjectionService::class)
            ->roomGroupRow($booking, collect([$room]));

        $roomRow = $row['rooms'][0];
        $this->assertSame($booking->id, $roomRow['booking_id']);
        $this->assertSame($booking->booking_code, $roomRow['booking_code']);
    }

    public function test_guest_projection_keeps_the_parent_booking_reference(): void
    {
        $booking = new Booking(['booking_name' => 'Booking test']);
        $booking->id = 43;

        $room = new BookingRoom(['booking_id' => $booking->id, 'room_number' => '102']);
        $room->id = 'G0000043';
        $room->setRelation('booking', $booking);

        $guest = new Guest(['full_name' => 'Khách test']);
        $assignment = new BookingRoomGuest(['booking_room_id' => $room->id]);
        $assignment->id = 1;
        $assignment->setRelation('bookingRoom', $room);
        $assignment->setRelation('guest', $guest);

        $row = app(GeneralSearchProjectionService::class)->guestRow($assignment);

        $this->assertSame($booking->id, $row['booking_id']);
        $this->assertSame($booking->booking_code, $row['booking_code']);
    }
}
