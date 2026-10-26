<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Department;
use App\Models\HotelService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayUseCheckInRoomChargeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'dayuse_checkin']);
        $role = Role::firstOrCreate(['code' => 'dayuse_checkin_role'], [
            'name' => 'Day Use Check-in', 'level' => 1, 'is_active' => true,
        ]);
        foreach (['fo.checkin', 'fo.service.add'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-10-23',
            'actual_date' => '2026-10-23',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Lễ tân']);
        HotelService::firstOrCreate(['code' => 'RM'], [
            'name' => 'Tiền phòng', 'department_id' => $department->id,
            'price' => 0, 'is_active' => true,
        ]);
    }

    public function test_day_use_checkin_requires_an_explicit_room_charge_decision(): void
    {
        [$booking, $room] = $this->makeDayUseStay('DU-ASK');

        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in")
            ->assertOk()
            ->assertJsonPath('needs_confirmation', true)
            ->assertJsonPath('confirmation_type', 'day_use_room_charge')
            ->assertJsonPath('day_use_room.room_number', 'DU-ASK');

        $this->assertDatabaseHas('booking_rooms', ['id' => $room->id, 'status' => BookingRoom::STATUS_BOOKED]);
        $this->assertDatabaseCount('service_bills', 0);
    }

    public function test_day_use_checkin_without_posting_room_charge_only_checks_in(): void
    {
        [$booking, $room] = $this->makeDayUseStay('DU-NO');

        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in", [
            'post_day_use_room_charge' => false,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('booking_rooms', ['id' => $room->id, 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->assertDatabaseCount('service_bills', 0);
    }

    public function test_day_use_checkin_with_posting_creates_one_room_charge_and_is_idempotent(): void
    {
        [$booking, $room] = $this->makeDayUseStay('DU-YES');

        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in", [
            'post_day_use_room_charge' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('booking_rooms', ['id' => $room->id, 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->assertSame(1, ServiceBill::query()
            ->where('RegisterId1', $booking->id)
            ->where('RentalRoomId1', $room->id)
            ->where('ServiceId', 'RM')
            ->where('Edit', 0)
            ->count());
    }

    /** @return array{0: Booking, 1: BookingRoom} */
    private function makeDayUseStay(string $roomNumber): array
    {
        $roomClass = RoomClass::firstOrCreate(['code' => 'DU'], ['name' => 'Day Use', 'is_active' => true]);
        $roomForm = RoomForm::firstOrCreate(['code' => 'DU'], ['name' => 'Day Use', 'is_active' => true]);
        Room::create([
            'room_number' => $roomNumber,
            'room_class_id' => $roomClass->id,
            'room_form_id' => $roomForm->id,
            'floor' => 1,
            'room_status_code' => 'vacant_ready',
            'is_active' => true,
            'clean_status' => 'clean',
        ]);

        $booking = Booking::create([
            'booking_code' => 'BK-' . $roomNumber,
            'booking_name' => 'Khách Day Use',
            'booking_date' => '2026-10-23',
            'arrival_date' => '2026-10-23',
            'departure_date' => '2026-10-23',
            'num_of_days' => 0,
            'is_day_use' => true,
            'created_by' => $this->user->username,
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $roomClass->id,
            'arrival_date' => '2026-10-23',
            'departure_date' => '2026-10-23',
            'is_day_use' => true,
            'rate' => 500000,
            'base_price' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        return [$booking, $room];
    }
}
