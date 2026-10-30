<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\RegistrationStatus;
use App\Models\RoomClass;
use App\Models\RoomRateCode;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 282 (note 23/10): gợi ý khách hiển thị giá phòng mới nhất và mã giá không bắt buộc khi lưu.
 */
class Row282GuestSuggestionRateCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;

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
        $this->roomClass = RoomClass::create(['code' => 'R282', 'name' => 'Row 282 room', 'is_active' => true]);
        RoomRateCode::create(['Ma' => 'STANDARD', 'Description' => 'Standard']);
    }

    private function stay(Guest $guest, string $arrival, string $departure, float $rate, array $attributes = []): BookingRoom
    {
        $booking = Booking::create([
            'booking_name' => 'Row 282', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'num_of_days' => 1, 'booking_date' => $arrival, 'created_by' => $this->user->username,
            'status' => Booking::STATUS_RESERVATION, 'registration_status_id' => 1,
        ]);
        $room = BookingRoom::create(array_merge([
            'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'status' => BookingRoom::STATUS_CHECKED_OUT,
            'rate' => $rate,
        ], $attributes));
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_ACTIVE]);

        return $room;
    }

    private function suggestion(string $q): array
    {
        return collect($this->getJson('/api/guests/search?q=' . urlencode($q))->assertOk()->json('data'))->first();
    }

    public function test_row_282_suggestion_shows_room_rate_after_rate_code_was_changed(): void
    {
        $guest = Guest::create(['full_name' => 'NGUYEN VAN GOI Y', 'id_number' => '079282000001']);
        $room = $this->stay($guest, '2026-08-01', '2026-08-02', 1500000);

        // Phòng cũ ban đầu giá 1tr5, sau đó chọn lại mã giá phòng giá 950k
        $room->update(['rate' => 950000, 'rate_code' => 'STANDARD']);

        $this->assertEquals(950000, $this->suggestion('GOI Y')['last_room_rate']);
        $this->assertEquals(950000, $this->suggestion('079282000001')['last_room_rate']);
    }

    public function test_row_282_suggestion_uses_most_recent_stay_and_skips_cancelled_rooms(): void
    {
        $guest = Guest::create(['full_name' => 'TRAN THI LUU TRU']);
        $this->stay($guest, '2026-07-01', '2026-07-03', 700000);
        $this->stay($guest, '2026-08-01', '2026-08-02', 950000);
        $this->stay($guest, '2026-08-05', '2026-08-06', 2000000, ['status' => BookingRoom::STATUS_CANCELLED]);

        $suggestion = $this->suggestion('LUU TRU');
        $this->assertEquals(950000, $suggestion['last_room_rate']);
        $this->assertSame('TRAN THI LUU TRU', $suggestion['full_name']);
    }

    public function test_row_282_guest_never_stayed_has_no_room_rate(): void
    {
        Guest::create(['full_name' => 'KHACH MOI CHUA O']);

        $this->assertNull($this->suggestion('CHUA O')['last_room_rate']);
    }

    private function inhouseGuestRoom(?string $storedRateCode): array
    {
        $guest = Guest::create(['full_name' => 'KHACH LUU THONG TIN']);
        $room = $this->stay($guest, '2026-08-09', '2026-08-10', 500000, ['status' => BookingRoom::STATUS_CHECKED_IN]);
        // Ghi thẳng DB để mô phỏng dữ liệu cũ (bỏ qua chuẩn hóa của model)
        DB::table('booking_rooms')->where('id', $room->id)->update(['rate_code' => $storedRateCode]);

        return [$room, $guest];
    }

    private function saveGuest(BookingRoom $room, Guest $guest, array $fields)
    {
        return $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", array_merge([
            'full_name' => 'KHACH LUU THONG TIN', 'phone' => '0909282282', 'rate' => 500000,
        ], $fields));
    }

    public function test_row_282_saving_guest_without_rate_code_is_allowed(): void
    {
        [$room, $guest] = $this->inhouseGuestRoom(null);

        $this->saveGuest($room, $guest, ['rate_code' => null])->assertOk();
        $this->saveGuest($room, $guest, ['rate_code' => ''])->assertOk();
        $this->assertSame('0909282282', $guest->fresh()->phone);
    }

    public function test_row_282_placeholder_rate_code_is_saved_as_no_rate_code(): void
    {
        [$room, $guest] = $this->inhouseGuestRoom('Vui lòng chọn giá phòng');

        $this->saveGuest($room, $guest, ['rate_code' => 'Vui lòng chọn giá phòng'])->assertOk();
        $this->assertNull($room->fresh()->rate_code);
    }

    public function test_row_282_unchanged_legacy_rate_code_does_not_block_saving(): void
    {
        // Mã giá đã lưu của phòng không còn trong danh mục: gửi lại nguyên vẹn vẫn lưu được thông tin khách
        [$room, $guest] = $this->inhouseGuestRoom('OLDCODE');

        $this->saveGuest($room, $guest, ['rate_code' => 'OLDCODE'])->assertOk();
        $this->assertSame('0909282282', $guest->fresh()->phone);
        $this->assertSame('OLDCODE', $room->fresh()->rate_code);
    }

    public function test_row_282_new_unknown_rate_code_is_still_rejected_but_valid_code_saves(): void
    {
        [$room, $guest] = $this->inhouseGuestRoom(null);

        $this->saveGuest($room, $guest, ['rate_code' => 'KHONGCO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rate_code');
        $this->saveGuest($room, $guest, ['rate_code' => 'STANDARD'])->assertOk();
        $this->assertSame('STANDARD', $room->fresh()->rate_code);
    }

    public function test_row_282_booking_room_never_stores_placeholder_as_rate_code(): void
    {
        $guest = Guest::create(['full_name' => 'KHACH TAO BOOKING']);
        $room = $this->stay($guest, '2026-08-09', '2026-08-10', 500000, ['rate_code' => 'Vui lòng chọn giá phòng']);
        $this->assertNull($room->fresh()->rate_code);

        $room->update(['rate_code' => '  ']);
        $this->assertNull($room->fresh()->rate_code);
        $room->update(['rate_code' => 'STANDARD']);
        $this->assertSame('STANDARD', $room->fresh()->rate_code);
    }

    public function test_row_282_migration_clears_stored_placeholder_rate_codes(): void
    {
        [$room] = $this->inhouseGuestRoom('Vui lòng chọn giá phòng');
        $guest = Guest::create(['full_name' => 'KHACH KHAC']);
        $other = $this->stay($guest, '2026-08-09', '2026-08-10', 500000, ['rate_code' => 'STANDARD']);

        $migration = require database_path('migrations/2026_10_29_100000_clear_rate_code_placeholder_on_booking_rooms.php');
        $migration->up();

        $this->assertNull(DB::table('booking_rooms')->where('id', $room->id)->value('rate_code'));
        $this->assertSame('STANDARD', DB::table('booking_rooms')->where('id', $other->id)->value('rate_code'));
    }
}
