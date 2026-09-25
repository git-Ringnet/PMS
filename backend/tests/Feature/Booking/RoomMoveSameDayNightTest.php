<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomMoveSameDayNightTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create(['code' => 'test_role', 'name' => 'Test Role', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['fo.booking.create', 'fo.booking.edit', 'fo.checkin', 'fo.checkout', 'fo.room.move'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'],
            ['id' => 100, 'name' => 'Moved'],
        ]);

        RegistrationStatus::create([
            'name' => 'Guaranteed',
            'booking_status_id' => 1,
            'is_availability' => true,
        ]);

        $roomForm = RoomForm::create(['name' => 'Standard Form']);
        $rc1 = RoomClass::create(['code' => 'SUPD', 'name' => 'Superior Double', 'is_active' => true]);
        $rc2 = RoomClass::create(['code' => 'SUPT', 'name' => 'Superior Twin', 'is_active' => true]);

        Room::create([
            'room_number' => '405',
            'room_class_id' => $rc1->id,
            'room_form_id' => $roomForm->id,
            'floor' => 4,
            'status' => 'available',
            'room_status_code' => 'vacant_clean',
        ]);

        Room::create([
            'room_number' => '1008',
            'room_class_id' => $rc2->id,
            'room_form_id' => $roomForm->id,
            'floor' => 10,
            'status' => 'available',
            'room_status_code' => 'vacant_ready',
        ]);

        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '1']);
        HotelConfig::create(['name' => 'AllowCheckinVacantClean', 'value' => '1']);
    }

    public function test_same_day_room_move_sets_actual_num_of_days_to_zero_for_old_room(): void
    {
        $sysDateStr = '2026-08-11';
        SystemDateRoll::create([
            'system_date' => "{$sysDateStr} 00:00:00",
            'actual_date' => "{$sysDateStr} 00:00:00",
            'shift' => '1',
            'username' => 'test_user',
        ]);

        $booking = Booking::create([
            'booking_name' => 'Test Move Same Day',
            'arrival_date' => $sysDateStr,
            'departure_date' => '2026-08-14',
            'booking_date' => $sysDateStr,
            'num_of_days' => 3,
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => 'test_user',
        ]);

        $oldRoom = BookingRoom::create([
            'id' => 'G0000019',
            'booking_id' => $booking->id,
            'room_number' => '405',
            'room_class_id' => 1,
            'arrival_date' => $sysDateStr,
            'departure_date' => '2026-08-14',
            'arrival_time' => '14:00:00',
            'departure_time' => '12:00:00',
            'NumOfDays' => 3,
            'ActutalNumOfDays' => 3,
            'rate' => 600000,
            'base_price' => 600000,
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'created_by' => 'test_user',
            'updated_by' => 'test_user',
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$oldRoom->id}/move", [
            'move_type' => 'available',
            'reason' => 'Chuyển sang phòng 1008',
            'target_room_number' => '1008',
            'is_change_rate' => false,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $oldRoomFresh = BookingRoom::withTrashed()->find($oldRoom->id);
        $this->assertNotNull($oldRoomFresh);
        $this->assertEquals(BookingRoom::STATUS_MOVED, (int) $oldRoomFresh->status);
        $this->assertEquals($sysDateStr, $oldRoomFresh->departure_date->toDateString());
        // Số đêm phòng 405 check-in và chuyển cùng ngày (11/08/2026) PHẢI là 0
        $this->assertEquals(0, (int) $oldRoomFresh->ActutalNumOfDays);

        $newRoom = BookingRoom::where('booking_id', $booking->id)->where('room_number', '1008')->first();
        $this->assertNotNull($newRoom);
        $this->assertEquals(BookingRoom::STATUS_CHECKED_IN, (int) $newRoom->status);
        $this->assertEquals(3, (int) $newRoom->ActutalNumOfDays);
    }

    public function test_subsequent_day_room_move_sets_correct_days_stayed_for_old_room(): void
    {
        $arrDateStr = '2026-08-11';
        $moveDateStr = '2026-08-13'; // Ở 2 đêm rồi chuyển
        SystemDateRoll::create([
            'system_date' => "{$moveDateStr} 00:00:00",
            'actual_date' => "{$moveDateStr} 00:00:00",
            'shift' => '1',
            'username' => 'test_user',
        ]);

        $booking = Booking::create([
            'booking_name' => 'Test Move After 2 Days',
            'arrival_date' => $arrDateStr,
            'departure_date' => '2026-08-15',
            'booking_date' => $arrDateStr,
            'num_of_days' => 4,
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => 'test_user',
        ]);

        $oldRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '405',
            'room_class_id' => 1,
            'arrival_date' => $arrDateStr,
            'departure_date' => '2026-08-15',
            'arrival_time' => '14:00:00',
            'departure_time' => '12:00:00',
            'NumOfDays' => 4,
            'ActutalNumOfDays' => 4,
            'rate' => 600000,
            'base_price' => 600000,
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'created_by' => 'test_user',
            'updated_by' => 'test_user',
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$oldRoom->id}/move", [
            'move_type' => 'available',
            'reason' => 'Chuyển sau 2 đêm',
            'target_room_number' => '1008',
            'is_change_rate' => false,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $oldRoomFresh = BookingRoom::withTrashed()->find($oldRoom->id);
        $this->assertEquals(BookingRoom::STATUS_MOVED, (int) $oldRoomFresh->status);
        // Ở từ 11 đến 13 -> 2 đêm
        $this->assertEquals(2, (int) $oldRoomFresh->ActutalNumOfDays);

        $newRoom = BookingRoom::where('booking_id', $booking->id)->where('room_number', '1008')->first();
        $this->assertEquals(2, (int) $newRoom->ActutalNumOfDays); // 13 đến 15 -> 2 đêm
    }
}

