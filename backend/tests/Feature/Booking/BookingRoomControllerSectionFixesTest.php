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
