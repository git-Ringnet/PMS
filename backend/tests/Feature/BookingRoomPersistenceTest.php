<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingRoomPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_checkout_updates_room_assignment_audit_fields(): void
    {
        $user = User::factory()->create(['username' => 'persistence_test']);
        $role = Role::firstOrCreate(
            ['code' => 'persistence_test'],
            ['name' => 'Persistence test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]
        );
        $permission = Permission::firstOrCreate(
            ['code' => 'fo.checkout'],
            ['name' => 'Check-out / Trả phòng', 'module' => 'FO']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
        ]);
        SystemDateRoll::create([
            'system_date' => '2026-08-04',
            'actual_date' => '2026-08-04',
            'shift' => '1',
            'username' => $user->username,
        ]);
        $form = RoomForm::create(['name' => 'Standard']);
        $class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        Room::create([
            'room_number' => '101',
            'room_form_id' => $form->id,
            'room_class_id' => $class->id,
            'floor' => 1,
            'status' => 'occupied',
        ]);
        $booking = Booking::create([
            'booking_name' => 'Booking child checkout',
            'booking_date' => '2026-08-01',
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-06',
            'status' => Booking::STATUS_CHECKIN,
            'created_by' => $user->username,
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-06',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $guest = Guest::create(['full_name' => 'Adult']);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
            'is_primary' => true,
        ]);
        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child',
            'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        $this->postJson("/api/booking-rooms/{$room->id}/children/{$child->id}/checkout")
            ->assertSuccessful();

        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $room->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_OUT,
            'actual_checkout_date' => '2026-08-04 00:00:00',
            'checkout_by' => $user->username,
        ]);
    }
}
