<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\Permission;
use App\Models\BookingRoomGuest;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\Role;
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
        $moveRole = Role::firstOrCreate(
            ['code' => 'fo_manager'],
            ['name' => 'Trưởng lễ tân', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]
        );
        $movePermission = Permission::firstOrCreate(
            ['code' => 'fo.room.move'],
            ['name' => 'Chuyển phòng', 'module' => 'FO']
        );
        $moveRole->permissions()->syncWithoutDetaching([$movePermission->id]);
        $this->user->roles()->attach($moveRole->id);
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
            'arrival_time'   => '08:30:00',
            'is_do_not_move' => 0,
        ]);

        $guest = Guest::create(['full_name' => 'Nguyen Van A']);
        BookingRoomGuest::create([
            'booking_room_id' => $bookingRoom->id,
            'guest_id'        => $guest->id,
            'is_primary'       => 1,
            'breakfast'        => 1,
        ]);
        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $bookingRoom->id,
            'full_name' => 'Child form A',
            'age_group' => 'child',
            'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$bookingRoom->id}/move", [
            'move_type'          => 'available',
            'target_room_number' => '102',
            'reason'             => 'Khách thích phòng tầng cao hơn',
            'selected_guest_ids' => [$guest->id],
            'selected_child_ids' => [$child->id],
            'is_change_rate'     => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Old room updated to status 100 (Chuyển phòng)
        $bookingRoom->refresh();
        $this->assertEquals(100, $bookingRoom->status);
        $this->assertEquals('2026-07-14', $bookingRoom->departure_date->toDateString());
        $this->assertSame('Khách thích phòng tầng cao hơn', $bookingRoom->reason);
        $this->assertNotNull($bookingRoom->CheckoutDate);
        $this->assertNotNull($bookingRoom->CheckoutTime);

        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $bookingRoom->id,
            'guest_id' => $guest->id,
            'actual_arrival_date' => '2026-07-10',
            'actual_arrival_time' => '08:30:00',
            'status' => 100,
        ]);

        // New room created for room 102
        $this->assertDatabaseHas('booking_rooms', [
            'booking_id'     => $booking->id,
            'room_number'    => '102',
            'arrival_date'   => '2026-07-14 00:00:00',
            'departure_date' => '2026-07-17 00:00:00',
            'CheckoutDate'   => '2026-07-17 00:00:00',
            'CheckoutTime'   => '12:00:00',
            'status'         => BookingRoom::STATUS_CHECKED_IN,
            'rate'           => 500000,
        ]);

        $newRoom = BookingRoom::where('booking_id', $booking->id)
            ->where('room_number', '102')
            ->firstOrFail();
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $newRoom->id,
            'guest_id' => $guest->id,
            'breakfast' => 1,
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $this->assertDatabaseHas('booking_children', [
            'id' => $child->id,
            'booking_room_id' => $newRoom->id,
            'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $bookingRoom->id,
            'status' => BookingRoom::STATUS_MOVED,
            'actual_checkout_date' => '2026-07-14 00:00:00',
            'checkout_by' => 'admin_test',
        ]);
        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $newRoom->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
            'actual_arrival_date' => '2026-07-14 00:00:00',
            'actual_checkout_date' => '2026-07-17 00:00:00',
            'actual_checkout_time' => '12:00:00',
        ]);
    }

    public function test_new_reservation_defaults_checkout_schedule_to_departure_and_noon(): void
    {
        $booking = Booking::create([
            'booking_name' => 'Booking default checkout',
            'booking_date' => '2026-07-10',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by' => 'admin_test',
            'status' => 0,
        ]);

        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '101',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->assertSame('2026-07-17', $room->CheckoutDate->toDateString());
        $this->assertSame('12:00:00', $room->CheckoutTime);
    }

    public function test_changing_active_room_departure_synchronizes_planned_checkout(): void
    {
        $booking = Booking::create([
            'booking_name' => 'Booking change departure',
            'booking_date' => '2026-07-10',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by' => 'admin_test',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '101',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $guest = Guest::create(['full_name' => 'Guest change departure']);
        $guestAssignment = BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child change departure',
            'age_group' => 'child',
            'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        $room->update(['departure_date' => '2026-07-19']);

        $this->assertSame('2026-07-19', $room->fresh()->CheckoutDate->toDateString());
        $this->assertSame('12:00:00', $room->fresh()->CheckoutTime);
        $this->assertSame('2026-07-19', $guestAssignment->fresh()->actual_checkout_date->toDateString());
        $this->assertSame('12:00:00', $guestAssignment->fresh()->actual_checkout_time);
        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $room->id,
            'actual_checkout_date' => '2026-07-19',
            'actual_checkout_time' => '12:00:00',
        ]);
    }

    public function test_move_to_room_preserves_guest_breakfast_and_child_breakfast_history(): void
    {
        $booking = Booking::create([
            'booking_name' => 'Booking move child data',
            'booking_date' => '2026-07-10',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by' => 'admin_test',
            'status' => Booking::STATUS_CHECKIN,
        ]);

        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '101',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'actual_arrival_date' => '2026-07-10',
            'arrival_time' => '08:00:00',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        $guest = Guest::create(['full_name' => 'Guest move child data']);
        $pivot = BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'is_primary' => 1,
            'breakfast' => 1,
        ]);
        $this->assertSame('2026-07-17', $pivot->actual_checkout_date->toDateString());
        $this->assertSame('12:00:00', $pivot->actual_checkout_time);
        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child move data',
            'age_group' => 'child',
        ]);
        BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-07-11',
            'breakfast' => 1,
            'is_free' => true,
            'is_extra_charge' => false,
            'is_room' => true,
            'amount' => 0,
        ]);

        $newRoom = $room->moveToRoom('102', '2026-07-14', 'admin_test', 'Khách yêu cầu đổi phòng');

        $this->assertSame('Khách yêu cầu đổi phòng', $room->fresh()->reason);
        $this->assertSame(BookingRoom::STATUS_MOVED, $room->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_MOVED, $pivot->fresh()->status);
        $this->assertSame('2026-07-14', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-07-17', $newRoom->CheckoutDate->toDateString());
        $this->assertSame('12:00:00', $newRoom->CheckoutTime);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $newRoom->id,
            'guest_id' => $guest->id,
            'breakfast' => 1,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $this->assertDatabaseHas('booking_children', [
            'id' => $child->id,
            'booking_room_id' => $newRoom->id,
        ]);
        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $room->id,
            'status' => 100,
            'actual_checkout_date' => '2026-07-14 00:00:00',
            'checkout_by' => 'admin_test',
        ]);
        $this->assertDatabaseHas('booking_room_children', [
            'booking_child_id' => $child->id,
            'booking_room_id' => $newRoom->id,
            'status' => 1,
            'actual_arrival_date' => '2026-07-14 00:00:00',
            'actual_checkout_date' => '2026-07-17 00:00:00',
            'actual_checkout_time' => '12:00:00',
            'checkin_by' => 'admin_test',
        ]);
        $this->assertDatabaseHas('booking_child_breakfast_details', [
            'booking_child_id' => $child->id,
            'breakfast' => 1,
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

    public function test_form_b_partial_guest_merge_keeps_old_room_checked_in()
    {
        $booking = Booking::create([
            'booking_name'   => 'Partial Merge Booking',
            'booking_date'   => '2026-07-10',
            'arrival_date'   => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by'     => 'admin_test',
            'updated_by'     => 'admin_test',
            'booking_code'   => 'BK006',
            'status'         => 0,
        ]);

        $room101 = BookingRoom::create([
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

        $room102 = BookingRoom::create([
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

        $guestA = Guest::create(['full_name' => 'Khach A']);
        $guestB = Guest::create(['full_name' => 'Khach B']);

        BookingRoomGuest::create([
            'booking_room_id' => $room101->id,
            'guest_id'        => $guestA->id,
            'is_primary'       => 1,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $room101->id,
            'guest_id'        => $guestB->id,
            'is_primary'       => 0,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // Merge ONLY guest B into room 102 (leaving guest A in room 101)
        $response = $this->postJson("/api/bookings/{$booking->id}/rooms/{$room101->id}/move", [
            'move_type'          => 'merge',
            'target_room_number' => '102',
            'reason'             => 'Gộp 1 người sang phòng 102',
            'selected_guest_ids' => [$guestB->id],
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        // Room 101 must REMAIN Checked-In (status 1) with 1 remaining adult
        $room101->refresh();
        $this->assertEquals(BookingRoom::STATUS_CHECKED_IN, $room101->status);
        $this->assertEquals(1, $room101->adults);

        // Guest B status in room 101 updated to 100
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $room101->id,
            'guest_id'        => $guestB->id,
            'status'          => 100,
        ]);

        // Guest A in room 101 remains status 1
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $room101->id,
            'guest_id'        => $guestA->id,
            'status'          => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // Target room 102 adults count increased by 1 (1 + 1 = 2)
        $room102->refresh();
        $this->assertEquals(2, $room102->adults);
    }

    public function test_merge_keeps_target_primary_guest_and_moves_only_source_guest_bills(): void
    {
        $sourceBooking = Booking::create([
            'booking_name' => 'Booking phòng nguồn',
            'booking_date' => '2026-07-10',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'created_by' => 'admin_test',
            'updated_by' => 'admin_test',
            'booking_code' => 'BK-SOURCE',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $targetBooking = Booking::create([
            'booking_name' => 'Booking phòng đích',
            'booking_date' => '2026-07-10',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-18',
            'created_by' => 'admin_test',
            'updated_by' => 'admin_test',
            'booking_code' => 'BK-TARGET',
            'status' => Booking::STATUS_CHECKIN,
        ]);
        $sourceRoom = BookingRoom::create([
            'booking_id' => $sourceBooking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '101',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-17',
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'adults' => 1,
        ]);
        $targetRoom = BookingRoom::create([
            'booking_id' => $targetBooking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => '102',
            'arrival_date' => '2026-07-10',
            'departure_date' => '2026-07-18',
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'adults' => 1,
        ]);
        $sourceGuest = Guest::create(['full_name' => 'Khách phòng nguồn']);
        $targetGuest = Guest::create(['full_name' => 'Khách chính phòng đích']);
        BookingRoomGuest::create([
            'booking_room_id' => $sourceRoom->id,
            'guest_id' => $sourceGuest->id,
            'is_primary' => true,
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        BookingRoomGuest::create([
            'booking_room_id' => $targetRoom->id,
            'guest_id' => $targetGuest->id,
            'is_primary' => true,
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        // Bill minibar của khách chuyển đang dùng chủ sở hữu gốc, chưa có thông tin chuyển bill.
        $sourceBill = ServiceBill::create([
            'Date' => '2026-07-14 00:00:00',
            'OpenTime' => '10:00',
            'Guest' => $sourceGuest->full_name,
            'DepartmentId' => 'FO',
            'ServiceId' => 'MB',
            'DescriptionServive' => 'Minibar phòng 101',
            'Quantity' => 1,
            'Amount' => 264500,
            'RentalRoomId1' => $sourceRoom->id,
            'CustomerId1' => $sourceGuest->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
            'Status' => 1,
            'Edit' => 0,
            'Username' => 'admin_test',
        ]);
        BookingRoomService::create([
            'booking_room_id' => $sourceRoom->id,
            'guest_id' => $sourceGuest->id,
            'service_bill_id' => $sourceBill->Ma,
            'service_code' => 'MB',
            'service_name' => 'Minibar',
            'service_date' => '2026-07-14',
            'quantity' => 1,
            'rate' => 264500,
            'total_amount' => 264500,
            'folio' => 1,
            'is_room' => 1,
            'is_posted' => 1,
        ]);

        // Bill có sẵn của phòng đích phải giữ nguyên cặp cột chuyển là NULL.
        $targetBill = ServiceBill::create([
            'Date' => '2026-07-14 00:00:00',
            'OpenTime' => '10:30',
            'Guest' => $targetGuest->full_name,
            'DepartmentId' => 'FO',
            'ServiceId' => 'EI',
            'DescriptionServive' => 'Dịch vụ phòng 102',
            'Quantity' => 1,
            'Amount' => 100000,
            'RentalRoomId1' => $targetRoom->id,
            'CustomerId1' => $targetGuest->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
            'Status' => 1,
            'Edit' => 0,
            'Username' => 'admin_test',
        ]);

        $this->postJson("/api/bookings/{$sourceBooking->id}/rooms/{$sourceRoom->id}/move", [
            'move_type' => 'merge',
            'target_room_number' => '102',
            'reason' => 'Chuyển khách sang phòng đang ở',
            'selected_guest_ids' => [$sourceGuest->id],
        ])->assertSuccessful()->assertJsonPath('success', true);

        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $targetRoom->id,
            'guest_id' => $targetGuest->id,
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('booking_room_guests', [
            'booking_room_id' => $targetRoom->id,
            'guest_id' => $sourceGuest->id,
            'is_primary' => 0,
        ]);
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $sourceBill->Ma,
            'RentalRoomId1' => $sourceRoom->id,
            'CustomerId1' => $sourceGuest->id,
            'RentalRoomId2' => $targetRoom->id,
            'CustomerId2' => $sourceGuest->id,
        ]);
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $targetBill->Ma,
            'RentalRoomId1' => $targetRoom->id,
            'CustomerId1' => $targetGuest->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'service_bill_id' => $sourceBill->Ma,
            'booking_room_id' => $targetRoom->id,
            'guest_id' => $sourceGuest->id,
        ]);
    }
}
