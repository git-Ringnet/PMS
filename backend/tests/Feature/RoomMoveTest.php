<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Guest;
use App\Models\BookingRoomGuest;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomMoveTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RoomClass $roomClass;
    protected Room $room101;
    protected Room $room102;
    protected Room $room103;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'admin_test']);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 100, 'name' => 'Chuyển phòng'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-07-14 00:00:00',
            'actual_date' => '2026-07-14 00:00:00',
            'shift'       => '1',
            'username'    => 'admin_test',
        ]);

        $roomForm = RoomForm::create(['name' => 'Standard Form']);
        $this->roomClass = RoomClass::create([
            'code'      => 'STD',
            'name'      => 'Standard Class',
            'is_active' => true,
        ]);

        // 101 (Available / Ready)
        $this->room101 = Room::create([
            'room_number'   => '101',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 1,
            'status'        => 'available',
        ]);

        // 102 (Dirty)
        $this->room102 = Room::create([
            'room_number'   => '102',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 1,
            'status'        => 'dirty',
        ]);

        // 103 (Occupied)
        $this->room103 = Room::create([
            'room_number'   => '103',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 1,
            'status'        => 'occupied',
        ]);
    }

    public function test_cannot_move_if_room_is_locked_do_not_move()
    {
        $booking = Booking::create([
            'booking_name'   => 'Khách Test 1',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK001',
            'status'         => 0,
        ]);

        $bookingRoom = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '101',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'is_do_not_move' => 1,
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/move", [
            'move_type'          => 'available',
            'target_room_number' => '102',
            'reason'             => 'Khách muốn đổi phòng',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => "Phòng 101 đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước."]);
    }

    public function test_cannot_move_to_dirty_unready_room()
    {
        $booking = Booking::create([
            'booking_name'   => 'Khách Test 2',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK002',
            'status'         => 0,
        ]);

        $bookingRoom = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '101',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'is_do_not_move' => 0,
        ]);

        // Try moving to 102 which is 'dirty'
        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/move", [
            'move_type'          => 'available',
            'target_room_number' => '102',
            'reason'             => 'Khách xin đổi phòng',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Vui lòng kiểm tra tình trạng phòng');
    }

    public function test_successful_form_a_room_move_to_available_ready_room()
    {
        $booking = Booking::create([
            'booking_name'   => 'Khách Test 3',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK003',
            'status'         => 0,
        ]);

        // Room 102 set to available
        $this->room102->update(['status' => 'available']);

        $bookingRoom = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '101',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'rate'           => 500000,
            'is_do_not_move' => 0,
        ]);

        $guest = Guest::create(['full_name' => 'Nguyen Van A']);
        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guest->id,
            'is_primary'       => 1,
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/move", [
            'move_type'          => 'available',
            'target_room_number' => '102',
            'reason'             => 'Khách thích phòng tầng cao hơn',
            'selected_guest_ids' => [$guest->id],
            'is_change_rate'     => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Old room updated to status 100 (Chuyển phòng)
        $bookingRoom->refresh();
        $this->assertEquals(100, $bookingRoom->status);
        $this->assertEquals('2026-07-13', $bookingRoom->departure_date->toDateString());

        // New room created for room 102
        $this->assertDatabaseHas('booking_rooms', [
            'booking_id'     => $booking->id,
            'room_number'    => '102',
            'arrival_date'   => '2026-07-14 00:00:00',
            'departure_date' => '2026-07-16 00:00:00',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'rate'           => 500000,
        ]);
    }

    public function test_form_b_merge_capacity_warning_and_successful_all_guest_merge()
    {
        $booking = Booking::create([
            'booking_name'   => 'Group Booking',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK004',
            'status'         => 0,
        ]);

        $bookingRoom101 = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '101',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'adults'         => 2,
            'rate'           => 500000,
            'is_do_not_move' => 0,
        ]);

        $bookingRoom102 = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '102',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'adults'         => 1,
            'rate'           => 500000,
            'is_do_not_move' => 0,
        ]);

        $guestA = Guest::create(['full_name' => 'Nguyen Van A']);
        $guestB = Guest::create(['full_name' => 'Nguyen Van B']);

        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom101->id,
            'guest_id'        => $guestA->id,
            'is_primary'       => 1,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom101->id,
            'guest_id'        => $guestB->id,
            'is_primary'       => 0,
        ]);

        // Target room 102 capacity is 2 (from max_guests/default). Merging 2 guests + 1 current = 3 > 2 capacity limit.
        // 1. Without confirm_exceed_capacity -> Expect require_capacity_confirm: true
        $response1 = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom101->id}/move", [
            'move_type'          => 'merge',
            'target_room_number' => '102',
            'reason'             => 'Gộp phòng',
            'selected_guest_ids' => [$guestA->id, $guestB->id],
        ]);

        $response1->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('require_capacity_confirm', true);

        // 2. With confirm_exceed_capacity: true -> Must succeed and merge all guests
        $response2 = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom101->id}/move", [
            'move_type'               => 'merge',
            'target_room_number'      => '102',
            'reason'                  => 'Gộp phòng đồng ý vượt số người',
            'selected_guest_ids'      => [$guestA->id, $guestB->id],
            'confirm_exceed_capacity' => true,
        ]);

        $response2->assertStatus(200)
            ->assertJsonPath('success', true);

        // Old room 101 must be checked out (status 100) since all guests moved
        $bookingRoom101->refresh();
        $this->assertEquals(100, $bookingRoom101->status);

        // Target room 102 updated adults count to 3
        $bookingRoom102->refresh();
        $this->assertEquals(3, $bookingRoom102->adults);
    }

    public function test_partial_guest_move_keeps_old_room_checked_in()
    {
        $booking = Booking::create([
            'booking_name'   => 'Multi Guest Booking',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK005',
            'status'         => 0,
        ]);

        $this->room102->update(['status' => 'available']);

        $bookingRoom = BookingRoom::create([
            'booking_id'     => $booking->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => '101',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'adults'         => 2,
            'rate'           => 500000,
            'is_do_not_move' => 0,
        ]);

        $guestA = Guest::create(['full_name' => 'Nguyen Van A']);
        $guestB = Guest::create(['full_name' => 'Tran Thi B']);

        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guestA->id,
            'is_primary'       => 1,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);

        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guestB->id,
            'is_primary'       => 0,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // Move ONLY guest B to room 102
        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/move", [
            'move_type'          => 'available',
            'target_room_number' => '102',
            'reason'             => 'Khách B muốn ở phòng riêng',
            'selected_guest_ids' => [$guestB->id],
            'is_change_rate'     => false,
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        // Old room 101 must REMAIN Checked-In (status 1) with reduced guest count
        $bookingRoom->refresh();
        $this->assertEquals(BookingRoom::STATUS_CHECKED_IN, $bookingRoom->status);
        $this->assertEquals(1, $bookingRoom->adults);

        // Moved guest B in old room marked as 100
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guestB->id,
            'status'          => 100,
        ]);

        // Remaining guest A in old room stays active (status 1)
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guestA->id,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);
    }
}
