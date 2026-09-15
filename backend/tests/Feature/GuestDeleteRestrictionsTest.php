<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomChild;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\RoomClass;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestDeleteRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->user = User::factory()->create(['username' => 'test_admin']);
        $role = Role::create([
            'code' => 'test_admin_role',
            'name' => 'Admin Role',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permission = Permission::firstOrCreate(
            ['code' => 'fo.booking.edit'],
            ['name' => 'Booking edit', 'module' => 'FO']
        );
        $role->permissions()->attach($permission->id);
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-08-09 00:00:00',
            'actual_date' => '2026-08-09 00:00:00',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        RegistrationStatus::create([
            'id' => 1,
            'booking_status_id' => 1,
            'name' => 'Guaranteed',
            'is_availability' => true,
        ]);

        $this->roomClass = RoomClass::create([
            'code' => 'DLX',
            'name' => 'Deluxe Room',
            'base_adults' => 2,
            'max_adults' => 3,
            'base_children' => 1,
            'max_children' => 2,
            'standard_capacity' => 2,
            'max_capacity' => 4,
            'is_active' => true,
        ]);
    }

    private function createBookingWithRoom(array $roomAttrs = []): array
    {
        $booking = Booking::create([
            'booking_name' => 'Nguyen Van A',
            'hotel_id' => 1,
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-11',
            'num_of_days' => 2,
            'booking_date' => '2026-08-09',
            'created_by' => $this->user->username,
            'status' => Booking::STATUS_CHECKIN,
            'registration_status_id' => 1,
        ]);

        $room = BookingRoom::create(array_merge([
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'room_number' => null,
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-11',
            'actual_arrival_date' => '2026-08-09',
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'adults' => 1,
            'children_qty' => 0,
            'rate' => 1000000,
        ], $roomAttrs));

        $guest = Guest::create([
            'full_name' => 'Guest One',
            'phone' => '0901234567',
        ]);

        $pivot = BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'status' => BookingRoomGuest::STATUS_CHECKED_IN,
            'actual_arrival_date' => '2026-08-09',
            'is_primary' => true,
        ]);

        return [$booking, $room, $guest, $pivot];
    }

    private function createServiceBill(array $attrs): ServiceBill
    {
        return ServiceBill::create(array_merge([
            'Guest' => 'Guest One',
            'Date' => '2026-08-09 10:00:00',
            'OpenTime' => '10:00',
            'DepartmentId' => 'FB',
            'ServiceId' => 'MN',
            'Username' => $this->user->username,
            'Edit' => 0,
            'Status' => 0,
            'Amount' => 50000,
        ], $attrs));
    }

    public function test_cannot_delete_guest_with_active_service_bill_customer1(): void
    {
        [$booking, $room, $guest] = $this->createBookingWithRoom();

        $this->createServiceBill([
            'CustomerId1' => (string) $guest->id,
            'Edit' => 0,
            'Status' => 0,
        ]);

        $response = $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Khách đã phát sinh hóa đơn hoặc thanh toán không thể xóa khách.',
            ]);
    }

    public function test_cannot_delete_guest_with_active_service_bill_customer2(): void
    {
        [$booking, $room, $guest] = $this->createBookingWithRoom();

        $this->createServiceBill([
            'CustomerId2' => (string) $guest->id,
            'Edit' => 0,
            'Status' => 0,
        ]);

        $response = $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Khách đã phát sinh hóa đơn hoặc thanh toán không thể xóa khách.',
            ]);
    }

    public function test_cannot_delete_guest_with_active_payment(): void
    {
        [$booking, $room, $guest] = $this->createBookingWithRoom();

        Payment::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'guest_id' => (string) $guest->id,
            'date' => '2026-08-09',
            'amount' => 500000,
            'edit_flag' => 0,
            'status' => 0,
            'payment_method' => 'CASH',
        ]);

        $response = $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Khách đã phát sinh hóa đơn hoặc thanh toán không thể xóa khách.',
            ]);
    }

    public function test_cannot_delete_guest_checked_in_previous_day(): void
    {
        [$booking, $room, $guest, $pivot] = $this->createBookingWithRoom([
            'arrival_date' => '2026-08-08',
            'actual_arrival_date' => '2026-08-08',
        ]);

        $pivot->update(['actual_arrival_date' => '2026-08-08']);

        $response = $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Chỉ cho phép xóa khách khi vừa mới check in trong ngày. Khách đã lưu trú qua ngày không thể xóa.',
            ]);
    }

    public function test_can_delete_guest_checked_in_same_day_without_bills(): void
    {
        [$booking, $room, $guest] = $this->createBookingWithRoom([
            'arrival_date' => '2026-08-09',
            'actual_arrival_date' => '2026-08-09',
        ]);

        $response = $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Đã xóa khách khỏi phòng và cơ sở dữ liệu.',
            ]);

        $this->assertDatabaseMissing('booking_room_guests', [
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
        ]);
    }

    public function test_cannot_delete_child_with_bill_or_payment(): void
    {
        [$booking, $room] = $this->createBookingWithRoom();

        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child Test',
            'age_group' => 'child',
            'child_status' => 1,
        ]);

        $this->createServiceBill([
            'CustomerId1' => (string) $child->id,
            'Edit' => 0,
            'Status' => 0,
        ]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}/children/{$child->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Khách đã phát sinh hóa đơn hoặc thanh toán không thể xóa khách.',
            ]);
    }

    public function test_cannot_delete_child_checked_in_previous_day(): void
    {
        [$booking, $room] = $this->createBookingWithRoom([
            'arrival_date' => '2026-08-08',
            'actual_arrival_date' => '2026-08-08',
        ]);

        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child Past',
            'age_group' => 'child',
            'child_status' => 1,
        ]);

        BookingRoomChild::where('booking_child_id', $child->id)->update([
            'actual_arrival_date' => '2026-08-08',
        ]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}/children/{$child->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Chỉ cho phép xóa khách khi vừa mới check in trong ngày. Khách đã lưu trú qua ngày không thể xóa.',
            ]);
    }

    public function test_can_delete_child_checked_in_same_day_without_bills(): void
    {
        [$booking, $room] = $this->createBookingWithRoom([
            'arrival_date' => '2026-08-09',
            'actual_arrival_date' => '2026-08-09',
        ]);

        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child Today',
            'age_group' => 'child',
            'child_status' => 1,
        ]);

        $response = $this->deleteJson("/api/bookings/{$booking->id}/children/{$child->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Đã xóa trẻ em.',
            ]);

        $this->assertDatabaseMissing('booking_children', [
            'id' => $child->id,
        ]);
    }
}
