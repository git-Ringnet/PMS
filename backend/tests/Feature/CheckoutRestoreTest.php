<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutRestoreTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private BookingRoom $bookingRoom;
    private Guest $earlyGuest;
    private Guest $lastGuest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['username' => 'checkout_restore_user']));
        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
        ]);
        SystemDateRoll::create(['system_date' => '2026-08-04', 'actual_date' => '2026-08-04', 'shift' => '1', 'username' => 'checkout_restore_user']);

        $roomForm = RoomForm::create(['name' => 'Standard']);
        $roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        Room::create(['room_number' => '101', 'room_form_id' => $roomForm->id, 'room_class_id' => $roomClass->id, 'floor' => 1, 'status' => 'checkout']);
        $this->booking = Booking::create([
            'booking_name' => 'Booking checkout', 'booking_date' => '2026-08-01',
            'arrival_date' => '2026-08-01', 'departure_date' => '2026-08-05',
            'status' => Booking::STATUS_CHECKOUT, 'created_by' => 'checkout_restore_user',
        ]);
        $this->bookingRoom = BookingRoom::create([
            'booking_id' => $this->booking->id, 'room_number' => '101', 'room_class_id' => $roomClass->id,
            'arrival_date' => '2026-08-01', 'departure_date' => '2026-08-05',
            'status' => BookingRoom::STATUS_CHECKED_OUT, 'CheckoutDate' => '2026-08-04', 'CheckoutTime' => '12:00:00',
        ]);
        $this->earlyGuest = Guest::create(['full_name' => 'Khach checkout som']);
        $this->lastGuest = Guest::create(['full_name' => 'Khach checkout cuoi']);
        BookingRoomGuest::create([
            'booking_room_id' => $this->bookingRoom->id, 'guest_id' => $this->earlyGuest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_OUT, 'actual_checkout_date' => '2026-08-04', 'actual_checkout_time' => '09:00:00',
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $this->bookingRoom->id, 'guest_id' => $this->lastGuest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_OUT, 'actual_checkout_date' => '2026-08-04', 'actual_checkout_time' => '12:00:00',
        ]);
    }

    public function test_restore_room_checkout_restores_only_the_last_checkout_group(): void
    {
        $this->postJson("/api/booking-rooms/{$this->bookingRoom->id}/restore-checkout")->assertSuccessful();

        $this->assertDatabaseHas('booking_rooms', ['id' => $this->bookingRoom->id, 'status' => BookingRoom::STATUS_CHECKED_IN, 'CheckoutDate' => null]);
        $this->assertDatabaseHas('booking_room_guests', ['booking_room_id' => $this->bookingRoom->id, 'guest_id' => $this->lastGuest->id, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        $this->assertDatabaseHas('booking_room_guests', ['booking_room_id' => $this->bookingRoom->id, 'guest_id' => $this->earlyGuest->id, 'status' => BookingRoomGuest::STATUS_CHECKED_OUT]);
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKIN]);
    }

    public function test_restore_room_checkout_is_blocked_when_the_room_is_already_inhouse(): void
    {
        BookingRoom::create([
            'booking_id' => $this->booking->id, 'room_number' => '101', 'room_class_id' => $this->bookingRoom->room_class_id,
            'arrival_date' => '2026-08-04', 'departure_date' => '2026-08-05', 'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        $this->postJson("/api/booking-rooms/{$this->bookingRoom->id}/restore-checkout")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_restore_room_checkout_is_blocked_when_the_room_is_locked(): void
    {
        RoomLock::create([
            'room_number' => '101', 'start_date' => '2026-08-04 00:00:00', 'end_date' => '2026-08-04 23:59:59',
            'lock_type' => 'OOO', 'is_active' => RoomLock::STATUS_ACTIVE,
        ]);

        $this->postJson("/api/booking-rooms/{$this->bookingRoom->id}/restore-checkout")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_restore_master_checkout_restores_child_rooms(): void
    {
        $this->postJson("/api/bookings/{$this->booking->id}/restore-checkout")->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKIN]);
        $this->assertDatabaseHas('booking_rooms', ['id' => $this->bookingRoom->id, 'status' => BookingRoom::STATUS_CHECKED_IN]);
    }
}
