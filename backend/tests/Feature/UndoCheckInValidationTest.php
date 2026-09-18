<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UndoCheckInValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Booking $booking;
    private BookingRoom $room;
    private Room $physicalRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'reception_user']);
        $role = \App\Models\Role::firstOrCreate(['code' => 'admin_test'], ['name' => 'Admin test', 'level' => 1, 'is_active' => true]);
        $perm = \App\Models\Permission::firstOrCreate(['code' => 'fo.checkin'], ['name' => 'fo.checkin', 'module' => 'FO']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $this->user->roles()->attach($role->id);

        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-09-16',
            'actual_date' => '2026-09-16',
            'shift'       => '1',
            'username'    => $this->user->username,
        ]);

        PaymentMethod::firstOrCreate(['code' => 'CA'], ['name' => 'Tiền mặt', 'is_active' => true]);

        $roomClass = \App\Models\RoomClass::firstOrCreate(['code' => 'STD'], ['name' => 'Standard Class', 'is_active' => true]);
        $roomForm = \App\Models\RoomForm::firstOrCreate(['code' => 'STD'], ['name' => 'Standard Form', 'is_active' => true]);

        $this->physicalRoom = Room::create([
            'room_number'      => '101',
            'room_class_id'    => $roomClass->id,
            'room_form_id'     => $roomForm->id,
            'floor'            => 1,
            'room_status_code' => 'occupied_ready',
            'is_active'        => true,
            'clean_status'     => 'clean',
        ]);

        \Illuminate\Support\Facades\DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Deleted'],
        ]);

        $this->booking = Booking::create([
            'booking_code'   => 'BK-101',
            'booking_name'   => 'Nguyen Van A',
            'status'         => Booking::STATUS_CHECKIN,
            'booking_date'   => '2026-09-16',
            'arrival_date'   => '2026-09-16',
            'departure_date' => '2026-09-18',
            'created_by'     => $this->user->username,
        ]);

        $this->room = BookingRoom::create([
            'booking_id'          => $this->booking->id,
            'room_number'         => '101',
            'room_class_id'       => $roomClass->id,
            'status'              => BookingRoom::STATUS_CHECKED_IN,
            'arrival_date'        => '2026-09-16',
            'actual_arrival_date' => '2026-09-16',
            'departure_date'      => '2026-09-18',
        ]);
    }

    public function test_undo_checkin_succeeds_and_updates_room_status_to_dirty(): void
    {
        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_dirty',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Hủy nhận phòng thành công!',
            ]);

        $this->assertDatabaseHas('booking_rooms', [
            'id'     => $this->room->id,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $this->assertDatabaseHas('rooms', [
            'room_number'      => '101',
            'room_status_code' => 'vacant_dirty',
        ]);
    }

    public function test_undo_checkin_succeeds_and_updates_room_status_to_clean(): void
    {
        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_clean',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('rooms', [
            'room_number'      => '101',
            'room_status_code' => 'vacant_clean',
        ]);
    }

    public function test_undo_checkin_fails_when_active_service_bill_exists(): void
    {
        ServiceBill::create([
            'Date'          => '2026-09-16 10:00:00',
            'OpenTime'      => '10:00',
            'Guest'         => 'Nguyen Van A',
            'Username'      => $this->user->username,
            'DepartmentId'  => 'FO',
            'RegisterID2'   => $this->booking->id,
            'RentalRoomId2' => (string) $this->room->id,
            'Amount'        => 100000,
            'Edit'          => 0,
            'ServiceId'     => 'FB',
            'DescriptionServive' => 'Coca Cola',
            'Quantity'      => 2,
        ]);

        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_dirty',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Hủy nhận phòng không thành công, phòng đã phát sinh dịch vụ hoặc đặt cọc. Vui lòng kiểm tra lại thông tin',
            ]);
    }

    public function test_undo_checkin_fails_when_active_payment_exists(): void
    {
        Payment::create([
            'booking_id'        => $this->booking->id,
            'booking_room_id'   => $this->room->id,
            'amount'            => 500000,
            'payment_method_id' => 'CA',
            'date'              => '2026-09-16',
            'payment_date'      => '2026-09-16',
            'edit_flag'         => 0,
            'status'            => 1,
        ]);

        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_dirty',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Hủy nhận phòng không thành công, phòng đã phát sinh dịch vụ hoặc đặt cọc. Vui lòng kiểm tra lại thông tin',
            ]);
    }

    public function test_undo_checkin_succeeds_if_bill_or_payment_has_been_cancelled(): void
    {
        // Bill đã bị xóa / hủy (Edit != 0)
        ServiceBill::create([
            'Date'          => '2026-09-16 10:00:00',
            'OpenTime'      => '10:00',
            'Guest'         => 'Nguyen Van A',
            'Username'      => $this->user->username,
            'DepartmentId'  => 'FO',
            'RegisterID2'   => $this->booking->id,
            'RentalRoomId2' => (string) $this->room->id,
            'Amount'        => 100000,
            'Edit'          => 1, // Đã hủy
            'ServiceId'     => 'FB',
        ]);

        // Payment đã bị hủy (edit_flag = 1 hoặc status = deleted)
        Payment::create([
            'booking_id'        => $this->booking->id,
            'booking_room_id'   => $this->room->id,
            'amount'            => 500000,
            'payment_method_id' => 'CA',
            'date'              => '2026-09-16',
            'payment_date'      => '2026-09-16',
            'edit_flag'         => 1, // Đã hủy
            'status'            => Payment::STATUS_DELETED,
        ]);

        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_dirty',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_undo_checkin_fails_if_checkin_was_past_date(): void
    {
        $this->room->update([
            'actual_arrival_date' => '2026-09-15',
            'arrival_date'        => '2026-09-15',
        ]);

        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/undo-checkin", [
            'room_status_code' => 'vacant_dirty',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Chỉ được hủy nhận phòng cho những phòng vừa mới nhận trong ngày.',
            ]);
    }
}
