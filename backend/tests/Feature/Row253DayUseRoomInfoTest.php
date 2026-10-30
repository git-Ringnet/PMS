<?php

namespace Tests\Feature;

use App\Models\Booking;
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
 * Dòng 253 (note 26/10): phòng in-house bật "Ở theo giờ" ở màn hình Thông tin,
 * mở lại phải giữ dấu check và số đêm = 0.
 */
class Row253DayUseRoomInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->user = User::factory()->withRole('fo_staff', ['fo.booking.edit'])->create();
        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-08-09 00:00:00',
            'actual_date' => '2026-08-09 00:00:00',
            'shift' => '1',
            'username' => $this->user->username,
        ]);
        RegistrationStatus::create(['id' => 1, 'booking_status_id' => 1, 'name' => 'Guaranteed', 'is_availability' => true]);
    }

    private function inhouseRoom(): array
    {
        $roomClass = RoomClass::create(['code' => 'DU', 'name' => 'Day use room', 'is_active' => true]);
        Room::create([
            'room_number' => '253', 'room_class_id' => $roomClass->id,
            'room_form_id' => RoomForm::create(['name' => 'Double'])->id,
            'floor' => 2, 'room_status_code' => 'occupied_dirty',
        ]);
        $booking = Booking::create([
            'booking_name' => 'Day use 253', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10',
            'num_of_days' => 1, 'booking_date' => '2026-08-09', 'created_by' => $this->user->username,
            'status' => Booking::STATUS_RESERVATION, 'registration_status_id' => 1,
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id, 'room_class_id' => $roomClass->id, 'room_number' => '253',
            'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10', 'status' => BookingRoom::STATUS_CHECKED_IN,
            'rate' => 500000, 'NumOfDays' => 1, 'ActutalNumOfDays' => 1,
        ]);
        $guest = Guest::create(['full_name' => 'DAY USE GUEST']);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_ACTIVE]);

        return [$booking, $room, $guest];
    }

    public function test_row_253_room_map_keeps_day_use_flag_of_room_after_saving_room_info(): void
    {
        [, $room, $guest] = $this->inhouseRoom();

        // Màn hình Thông tin: bật "Phòng theo giờ" -> ngày đi = ngày đến
        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'DAY USE GUEST', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-09',
            'is_day_use' => true, 'rate' => 500000,
        ])->assertSuccessful();
        $this->normalizeDates($room);

        $mapRoom = $this->roomMapRoom();
        $this->assertSame('2026-08-09', $mapRoom['departure_date']);
        $this->assertTrue($mapRoom['is_day_use']);
        $this->assertSame(0, (int) $mapRoom['nights']);
    }

    public function test_row_253_overnight_room_is_not_flagged_day_use_on_room_map(): void
    {
        [, $room] = $this->inhouseRoom();
        $this->normalizeDates($room);

        $mapRoom = $this->roomMapRoom();
        $this->assertFalse($mapRoom['is_day_use']);
        $this->assertSame(1, (int) $mapRoom['nights']);
    }

    public function test_row_253_turning_day_use_off_restores_overnight_room(): void
    {
        [, $room, $guest] = $this->inhouseRoom();
        $room->update(['departure_date' => '2026-08-09', 'is_day_use' => true]);

        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'DAY USE GUEST', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10',
            'is_day_use' => false, 'rate' => 500000,
        ])->assertSuccessful();
        $this->normalizeDates($room);

        $mapRoom = $this->roomMapRoom();
        $this->assertFalse($mapRoom['is_day_use']);
        $this->assertSame(1, (int) $mapRoom['nights']);
    }

    public function test_row_253_day_use_room_cannot_keep_a_later_departure_date(): void
    {
        [, $room, $guest] = $this->inhouseRoom();

        // Đang bật Ở theo giờ mà vẫn gửi ngày đi khác ngày đến -> ngày đi bị ép về ngày đến
        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'DAY USE GUEST', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-11',
            'is_day_use' => true, 'rate' => 500000,
        ])->assertSuccessful();

        $room->refresh();
        $this->assertSame('2026-08-09', $room->departure_date->toDateString());
        $this->assertSame(0, (int) $room->ActutalNumOfDays);
        $this->assertSame(0, (int) $room->NumOfDays);
    }

    public function test_row_253_day_close_list_uses_room_dates_after_late_out_extension(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->grant('fo.night_audit');

        // Phòng ở 2 đêm (08/08 - 10/08), late out thêm 1 đêm tại màn hình Sang ngày
        $room->update(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10']);
        $booking->update(['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'num_of_days' => 2]);
        $this->postJson('/api/night-audit/extend-stay', ['booking_room_id' => $room->id, 'nights' => 1])
            ->assertOk()
            ->assertJsonPath('departure_date', '2026-08-11');

        $room->refresh();
        $this->assertSame('2026-08-11', $room->departure_date->toDateString());
        $this->assertSame(3, (int) $room->ActutalNumOfDays);
        // Booking vẫn lưu 2 đêm -> màn hình Sang ngày phải tính theo ngày của phòng (3 đêm)
        $this->assertSame(2, (int) $booking->fresh()->num_of_days);
        $this->assertSame('2026-08-11', $this->localDate($this->dayCloseRoom($room->id)['departure_date']));
    }

    public function test_row_253_shortened_stay_updates_room_dates_for_day_close(): void
    {
        [$booking, $room, $guest] = $this->inhouseRoom();

        // Ban đầu ở 4 đêm (09/08 - 13/08), chỉnh ngày đi còn 2 đêm
        $room->update(['departure_date' => '2026-08-13']);
        $booking->update(['departure_date' => '2026-08-13', 'num_of_days' => 4]);
        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'DAY USE GUEST', 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-11', 'rate' => 500000,
        ])->assertSuccessful();

        $this->assertSame(2, (int) $room->fresh()->ActutalNumOfDays);
        $this->assertSame('2026-08-11', $this->localDate($this->dayCloseRoom($room->id)['departure_date']));
    }

    private function grant(string $code): void
    {
        $permission = \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
        \App\Models\Role::where('code', 'fo_staff')->first()->permissions()->syncWithoutDetaching([$permission->id]);
    }

    private function dayCloseRoom(string $roomId): array
    {
        $response = $this->getJson('/api/bookings?stay_only=1')->assertSuccessful();
        $booking = collect($response->json('data'))->first();
        $rooms = $booking['booking_rooms'] ?? $booking['bookingRooms'] ?? [];

        return collect($rooms)->firstWhere('id', $roomId);
    }

    // API trả ngày dạng UTC; màn hình Sang ngày đổi về giờ khách sạn trước khi tính số đêm
    private function localDate(string $value): string
    {
        return \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateString();
    }

    // SQLite giữ hậu tố 00:00:00 trong cột DATE; MySQL lưu YYYY-MM-DD.
    private function normalizeDates(BookingRoom $room): void
    {
        $fresh = $room->fresh();
        \Illuminate\Support\Facades\DB::table('booking_rooms')->where('id', $room->id)->update([
            'arrival_date' => $fresh->arrival_date->toDateString(),
            'departure_date' => $fresh->departure_date->toDateString(),
        ]);
    }

    private function roomMapRoom(): array
    {
        return collect($this->getJson('/api/rooms?date=2026-08-09')->assertSuccessful()->json('data'))
            ->firstWhere('room_number', '253');
    }
}
