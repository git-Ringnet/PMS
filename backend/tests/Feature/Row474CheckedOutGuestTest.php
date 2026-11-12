<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 474: khách/trẻ em đã check out (status = 2) không được sửa/xóa;
 * gia hạn ở màn hình thông tin phòng không được trùng số phòng.
 */
class Row474CheckedOutGuestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->user = User::factory()->create(['username' => 'row474_user']);
        $role = Role::create(['code' => 'row474_role', 'name' => 'Row 474', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['fo.booking.edit', 'fo.guest.edit'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        SystemDateRoll::create(['system_date' => '2026-08-09 00:00:00', 'actual_date' => '2026-08-09 00:00:00', 'shift' => '1', 'username' => 'row474_user']);
        RegistrationStatus::create(['id' => 1, 'booking_status_id' => 1, 'name' => 'Guaranteed', 'is_availability' => true]);
        $this->roomClass = RoomClass::create(['code' => 'DLX', 'name' => 'Deluxe', 'base_adults' => 2, 'max_adults' => 3, 'standard_capacity' => 2, 'max_capacity' => 4, 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Form']);
        foreach (['401', '402'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->roomClass->id, 'room_form_id' => $form->id, 'floor' => 4, 'status' => 'available']);
        }
    }

    private function booking(string $arrival, string $departure, int $status = Booking::STATUS_CHECKIN): Booking
    {
        return Booking::create([
            'booking_name' => 'Row474', 'hotel_id' => 1, 'arrival_date' => $arrival, 'departure_date' => $departure,
            'num_of_days' => 2, 'booking_date' => $arrival, 'created_by' => 'row474_user', 'status' => $status, 'registration_status_id' => 1,
        ]);
    }

    private function room(Booking $booking, string $roomNumber, string $arrival, string $departure, int $status): BookingRoom
    {
        return BookingRoom::create([
            'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id, 'room_number' => $roomNumber,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'actual_arrival_date' => $arrival,
            'status' => $status, 'adults' => 3, 'rate' => 1000000,
        ]);
    }

    private function inhouseRoom(Booking $booking, string $roomNumber): BookingRoom
    {
        return $this->room($booking, $roomNumber, '2026-08-09', '2026-08-11', BookingRoom::STATUS_CHECKED_IN);
    }

    private function guest(BookingRoom $room, string $name, int $status, bool $primary = false): Guest
    {
        $guest = Guest::create(['full_name' => $name]);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id, 'guest_id' => $guest->id, 'status' => $status,
            'actual_arrival_date' => '2026-08-09', 'is_primary' => $primary,
        ]);

        return $guest;
    }

    public function test_checked_out_guest_cannot_be_edited_or_deleted(): void
    {
        $room = $this->inhouseRoom($this->booking('2026-08-09', '2026-08-11'), '401');
        $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->guest($room, 'Guest 2', BookingRoomGuest::STATUS_CHECKED_IN);
        $out = $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);

        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$out->id}", ['full_name' => 'Doi ten'])->assertStatus(422);
        $this->assertSame('Guest 3', $out->fresh()->full_name);
        $this->deleteJson("/api/booking-rooms/{$room->id}/guests/{$out->id}")->assertStatus(422);
        $this->assertNotNull(Guest::find($out->id));

        $active = BookingRoomGuest::where('booking_room_id', $room->id)->where('status', BookingRoomGuest::STATUS_CHECKED_IN)->first();
        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$active->guest_id}", ['full_name' => 'Doi ten dc'])->assertSuccessful();
    }

    public function test_bulk_update_rejects_checked_out_guest_and_child(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-11');
        $room = $this->inhouseRoom($booking, '401');
        $active = $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $out = $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);
        $child = BookingChild::create(['booking_id' => $booking->id, 'booking_room_id' => $room->id, 'full_name' => 'Child 1', 'age_group' => 'child', 'child_status' => 2]);

        $this->postJson("/api/bookings/{$booking->id}/bulk-update-guests", ['guests' => [['id' => $out->id, 'full_name' => 'X']]])->assertStatus(422);
        $this->postJson("/api/bookings/{$booking->id}/bulk-update-guests", ['children' => [['id' => $child->id, 'full_name' => 'X']]])->assertStatus(422);
        $this->assertSame('Guest 3', $out->fresh()->full_name);
        $this->assertSame('Child 1', $child->fresh()->full_name);

        $this->postJson("/api/bookings/{$booking->id}/bulk-update-guests", ['guests' => [['id' => $active->id, 'full_name' => 'Guest 1 moi']]])->assertSuccessful();
        $this->assertSame('Guest 1 moi', $active->fresh()->full_name);
    }

    public function test_checked_out_child_cannot_be_edited_or_deleted(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-11');
        $room = $this->inhouseRoom($booking, '401');
        $child = BookingChild::create(['booking_id' => $booking->id, 'booking_room_id' => $room->id, 'full_name' => 'Child 1', 'age_group' => 'child', 'child_status' => 2]);

        $this->putJson("/api/booking-children/{$child->id}", ['full_name' => 'Doi'])->assertStatus(422);
        $this->deleteJson("/api/bookings/{$booking->id}/children/{$child->id}")->assertStatus(422);
        $this->assertNotNull(BookingChild::find($child->id));
    }

    public function test_booking_guests_exposes_stay_status_per_guest(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-11');
        $room = $this->inhouseRoom($booking, '401');
        $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);

        $guests = collect($this->getJson("/api/bookings/{$booking->id}/guests")->assertOk()->json('data.0.guests'));

        $this->assertEqualsCanonicalizing([1, 2], $guests->pluck('stay_status')->all());
    }

    public function test_extending_room_into_another_bookings_room_number_is_rejected(): void
    {
        $mine = $this->inhouseRoom($this->booking('2026-08-09', '2026-08-11'), '401');
        $primary = $this->guest($mine, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->room($this->booking('2026-08-12', '2026-08-14', Booking::STATUS_RESERVATION), '401', '2026-08-12', '2026-08-14', BookingRoom::STATUS_BOOKED);

        // Gia hạn sang 13/08 chồng lên booking khác đã giữ phòng 401 (12-14/08) -> bị từ chối, ngày đi giữ nguyên.
        $this->putJson("/api/booking-rooms/{$mine->id}/guests/{$primary->id}", ['departure_date' => '2026-08-13'])->assertStatus(422);
        $this->assertSame('2026-08-11', $mine->fresh()->departure_date->toDateString());

        // Gia hạn đến 12/08 (ngày đi trùng ngày đến của booking sau) vẫn được.
        $this->putJson("/api/booking-rooms/{$mine->id}/guests/{$primary->id}", ['departure_date' => '2026-08-12'])->assertSuccessful();
        $this->assertSame('2026-08-12', $mine->fresh()->departure_date->toDateString());
    }

    /** Hết phòng loại DLX đêm 11/08: phòng 402 và một phòng chưa gán số đều đã được giữ. */
    private function fillRoomClassOnAugust11(): void
    {
        $other = $this->booking('2026-08-11', '2026-08-13', Booking::STATUS_RESERVATION);
        $this->room($other, '402', '2026-08-11', '2026-08-13', BookingRoom::STATUS_BOOKED);
        BookingRoom::create([
            'booking_id' => $other->id, 'room_class_id' => $this->roomClass->id, 'room_number' => null,
            'arrival_date' => '2026-08-11', 'departure_date' => '2026-08-13',
            'status' => BookingRoom::STATUS_BOOKED, 'adults' => 2, 'rate' => 1000000,
        ]);
    }

    public function test_extension_over_room_class_av_is_blocked_when_over_is_not_allowed(): void
    {
        \App\Models\HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0', 'is_visible' => true]);
        $mine = $this->inhouseRoom($this->booking('2026-08-09', '2026-08-11'), '401');
        $primary = $this->guest($mine, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->fillRoomClassOnAugust11();

        $this->putJson("/api/booking-rooms/{$mine->id}/guests/{$primary->id}", ['departure_date' => '2026-08-12', 'confirm_overbooking' => true])
            ->assertStatus(422);
        $this->assertSame('2026-08-11', $mine->fresh()->departure_date->toDateString());
    }

    public function test_extension_over_room_class_av_requires_confirmation_when_over_is_allowed(): void
    {
        \App\Models\HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1', 'is_visible' => true]);
        $mine = $this->inhouseRoom($this->booking('2026-08-09', '2026-08-11'), '401');
        $primary = $this->guest($mine, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->fillRoomClassOnAugust11();

        // Chưa xác nhận: hỏi lại, không lưu gì (kể cả thông tin khách gửi kèm).
        $this->putJson("/api/booking-rooms/{$mine->id}/guests/{$primary->id}", ['departure_date' => '2026-08-12', 'full_name' => 'Doi ten'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'OVERBOOKING_CONFIRMATION_REQUIRED');
        $this->assertSame('2026-08-11', $mine->fresh()->departure_date->toDateString());
        $this->assertSame('Guest 1', $primary->fresh()->full_name);

        // Đã xác nhận: lưu.
        $this->putJson("/api/booking-rooms/{$mine->id}/guests/{$primary->id}", ['departure_date' => '2026-08-12', 'confirm_overbooking' => true])
            ->assertSuccessful();
        $this->assertSame('2026-08-12', $mine->fresh()->departure_date->toDateString());
    }

    public function test_old_registration_list_exposes_checked_out_guest_of_inhouse_room(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-11');
        $room = $this->inhouseRoom($booking, '401');
        $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $out = $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);

        $rooms = $this->getJson('/api/bookings?status=0,1,2,3,4&with_billing=1&stay_only=1')->assertOk()->json('data');
        $bookingRow = collect($rooms)->firstWhere('id', $booking->id);
        $guests = collect($bookingRow['booking_rooms'][0]['guests'] ?? []);

        // Màn hình hóa đơn - Đăng ký cũ cần status từng khách để hiện riêng khách đã check out lẻ.
        $this->assertEqualsCanonicalizing([1, 2], $guests->pluck('status')->map(fn ($s) => (int) $s)->all());
        $this->assertTrue($guests->contains(fn ($g) => (string) ($g['guest_id'] ?? '') === (string) $out->id));
    }

    public function test_room_map_counts_only_guests_still_in_the_room(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-11');
        $room = $this->inhouseRoom($booking, '401');
        $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $this->guest($room, 'Guest 2', BookingRoomGuest::STATUS_CHECKED_IN);
        $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);
        $child = BookingChild::create(['booking_id' => $booking->id, 'booking_room_id' => $room->id, 'full_name' => 'Child 1', 'age_group' => 'child', 'child_status' => 2]);
        \App\Models\BookingRoomChild::where('booking_child_id', $child->id)->update(['status' => 1]);

        $rooms = collect($this->getJson('/api/rooms?date=2026-08-10')->assertOk()->json('data'));
        $row = $rooms->firstWhere('room_number', '401');

        $this->assertSame(2, (int) $row['adults']);
        $this->assertSame(0, (int) $row['children']);
        $this->assertSame(2, (int) $row['guest_count']);
    }

    public function test_old_registration_departure_filter_includes_booking_with_guest_checked_out_in_range(): void
    {
        $booking = $this->booking('2026-08-09', '2026-08-20');
        $room = $this->room($booking, '401', '2026-08-09', '2026-08-20', BookingRoom::STATUS_CHECKED_IN);
        $this->guest($room, 'Guest 1', BookingRoomGuest::STATUS_CHECKED_IN, true);
        $out = $this->guest($room, 'Guest 3', BookingRoomGuest::STATUS_CHECKED_OUT);
        BookingRoomGuest::where('guest_id', $out->id)->update(['actual_checkout_date' => '2026-08-10 11:00:00']);

        $inRange = collect($this->getJson('/api/bookings?status=0,1,2,3,4&with_billing=1&stay_only=1&from_date=2026-08-10&to_date=2026-08-10&date_type=departure')->assertOk()->json('data'));
        $outRange = collect($this->getJson('/api/bookings?status=0,1,2,3,4&with_billing=1&stay_only=1&from_date=2026-08-12&to_date=2026-08-12&date_type=departure')->assertOk()->json('data'));

        $this->assertNotNull($inRange->firstWhere('id', $booking->id));
        $this->assertNull($outRange->firstWhere('id', $booking->id));
    }
}
