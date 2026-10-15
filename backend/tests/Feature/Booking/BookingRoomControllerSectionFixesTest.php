<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\BookingStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingRoomControllerSectionFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RoomClass $roomClass;

    private RoomForm $roomForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'booking_room_sections_user']);
        $role = Role::firstOrCreate(
            ['code' => 'booking_room_sections_test'],
            ['name' => 'Booking room sections test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true],
        );

        $permissions = collect([
            ['code' => 'fo.booking.edit', 'name' => 'Edit booking', 'module' => 'FO'],
            ['code' => 'fo.checkin', 'name' => 'Check-in', 'module' => 'FO'],
        ])->map(fn (array $permission) => Permission::firstOrCreate(
            ['code' => $permission['code']],
            ['name' => $permission['name'], 'module' => $permission['module']],
        ));
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $this->user->roles()->syncWithoutDetaching([$role->id]);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insert([
            ['id' => Booking::STATUS_RESERVATION, 'name' => 'Reservation'],
            ['id' => Booking::STATUS_CHECKIN, 'name' => 'Checked In'],
            ['id' => Booking::STATUS_CHECKOUT, 'name' => 'Checked Out'],
            ['id' => Booking::STATUS_DELETED, 'name' => 'Deleted'],
            ['id' => Booking::STATUS_NO_SHOW, 'name' => 'No Show'],
            ['id' => Booking::STATUS_TRANSFER, 'name' => 'Transfer'],
        ]);
        SystemDateRoll::create([
            'system_date' => '2026-08-07',
            'actual_date' => '2026-08-07',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        $this->roomForm = RoomForm::create(['name' => 'Standard']);
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
    }

    public function test_auto_assign_uses_numeric_floor_and_natural_room_order(): void
    {
        foreach ([
            ['room_number' => '1005', 'floor' => '10'],
            ['room_number' => '206', 'floor' => '2'],
            ['room_number' => '305', 'floor' => '3'],
            ['room_number' => '106', 'floor' => '1'],
            ['room_number' => '205', 'floor' => '2'],
            ['room_number' => '105', 'floor' => '1'],
        ] as $roomData) {
            Room::create([
                ...$roomData,
                'room_form_id' => $this->roomForm->id,
                'room_class_id' => $this->roomClass->id,
            ]);
        }

        $booking = $this->makeBooking();
        $bookingRooms = collect(range(1, 5))->map(fn (int $number) => BookingRoom::create([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]));

        $assigned = [];
        foreach ($bookingRooms as $bookingRoom) {
            $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/auto-assign")
                ->assertSuccessful();
            $assigned[] = $bookingRoom->fresh()->room_number;
        }

        $this->assertSame(['105', '106', '205', '206', '305'], $assigned);
    }

    public function test_checking_in_one_room_sets_booking_header_even_when_other_rooms_are_booked(): void
    {
        foreach (['101', '102', '103'] as $roomNumber) {
            Room::create([
                'room_number' => $roomNumber,
                'room_form_id' => $this->roomForm->id,
                'room_class_id' => $this->roomClass->id,
                'floor' => '1',
            ]);
        }

        $booking = $this->makeBooking();
        $bookingRooms = collect(['101', '102', '103'])->map(fn (string $roomNumber) => BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]));

        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$bookingRooms[0]->id}/check-in")
            ->assertSuccessful();

        $this->assertDatabaseHas('booking_rooms', [
            'id' => $bookingRooms[0]->id,
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $bookingRooms[1]->id,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    public function test_status_sync_keeps_checkin_while_any_room_is_inhouse_and_uses_fallback_when_last_room_is_undone(): void
    {
        $booking = $this->makeBooking(['status' => Booking::STATUS_CHECKIN]);
        $rooms = collect([
            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_class_id' => $this->roomClass->id,
                'arrival_date' => '2026-08-07',
                'departure_date' => '2026-08-08',
                'status' => BookingRoom::STATUS_CHECKED_IN,
            ]),
            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_class_id' => $this->roomClass->id,
                'arrival_date' => '2026-08-07',
                'departure_date' => '2026-08-08',
                'status' => BookingRoom::STATUS_BOOKED,
            ]),
        ]);
        $service = app(BookingStatusSyncService::class);

        $service->sync($booking, Booking::STATUS_RESERVATION);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => Booking::STATUS_CHECKIN]);

        $rooms[0]->update(['status' => BookingRoom::STATUS_BOOKED]);
        $service->sync($booking, Booking::STATUS_RESERVATION);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => Booking::STATUS_RESERVATION]);
    }

    public function test_checkin_and_undo_checkin_updates_actual_arrival_time_and_audit_fields_in_booking_rooms_and_guests(): void
    {
        $room = Room::create([
            'room_number' => '201',
            'room_form_id' => $this->roomForm->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '2',
            'room_status_code' => 'vacant_ready',
        ]);

        $booking = $this->makeBooking();
        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '201',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'arrival_time' => null,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $guest = \App\Models\Guest::create([
            'full_name' => 'Nguyen Van A',
            'guest_status' => \App\Models\Guest::STATUS_ACTIVE,
        ]);

        $roomGuest = \App\Models\BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id' => $guest->id,
            'is_primary' => true,
            'status' => BookingRoom::STATUS_BOOKED,
            'actual_arrival_date' => '2026-08-07',
            'actual_arrival_time' => null,
            'checkin_by' => null,
        ]);

        $child = \App\Models\BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $bookingRoom->id,
            'full_name' => 'Nguyen Be B',
            'child_status' => \App\Models\BookingRoomGuest::STATUS_ACTIVE,
        ]);

        $childAssignment = \App\Models\BookingRoomChild::where('booking_child_id', $child->id)
            ->where('booking_room_id', $bookingRoom->id)
            ->first();

        // Check-in
        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/check-in", [
            'arrival_time' => '14:35:00',
        ])->assertSuccessful();

        // 1. Kiểm tra bảng booking_rooms
        $freshRoom = $bookingRoom->fresh();
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $freshRoom->status);
        $this->assertSame('14:35:00', $freshRoom->arrival_time);
        $this->assertSame('2026-08-07', $freshRoom->actual_arrival_date->toDateString());
        $this->assertSame($this->user->username, $freshRoom->check_in_user);

        // 2. Kiểm tra bảng booking_room_guests
        $freshGuest = $roomGuest->fresh();
        $this->assertSame(\App\Models\BookingRoomGuest::STATUS_CHECKED_IN, (int) $freshGuest->status);
        $this->assertSame('14:35:00', $freshGuest->actual_arrival_time);
        $this->assertSame('2026-08-07', $freshGuest->actual_arrival_date->toDateString());
        $this->assertSame($this->user->username, $freshGuest->checkin_by);

        // 3. Kiểm tra bảng booking_room_children & booking_children
        $freshChildAssignment = $childAssignment->fresh();
        $this->assertSame(\App\Models\BookingRoomGuest::STATUS_CHECKED_IN, (int) $freshChildAssignment->status);
        $this->assertSame('14:35:00', $freshChildAssignment->actual_arrival_time);
        $this->assertSame('2026-08-07', $freshChildAssignment->actual_arrival_date->toDateString());
        $this->assertSame($this->user->username, $freshChildAssignment->checkin_by);
        $this->assertSame(\App\Models\BookingRoomGuest::STATUS_CHECKED_IN, (int) $child->fresh()->child_status);

        // 4. Undo check-in
        $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/undo-checkin", [
            'room_status_code' => 'vacant_ready',
        ])->assertSuccessful();

        $freshRoomAfterUndo = $bookingRoom->fresh();
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $freshRoomAfterUndo->status);
        $this->assertNull($freshRoomAfterUndo->check_in_user);

        $freshGuestAfterUndo = $roomGuest->fresh();
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $freshGuestAfterUndo->status);
        $this->assertNull($freshGuestAfterUndo->actual_arrival_time);
        $this->assertNull($freshGuestAfterUndo->checkin_by);

        $freshChildAfterUndo = $childAssignment->fresh();
        $this->assertSame(\App\Models\BookingRoomGuest::STATUS_ACTIVE, (int) $freshChildAfterUndo->status);
        $this->assertNull($freshChildAfterUndo->actual_arrival_time);
        $this->assertNull($freshChildAfterUndo->checkin_by);
        $this->assertSame(\App\Models\BookingRoomGuest::STATUS_ACTIVE, (int) $child->fresh()->child_status);
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Booking room sections',
            'booking_date' => '2026-08-07',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => $this->user->username,
        ], $attributes));
    }
}
