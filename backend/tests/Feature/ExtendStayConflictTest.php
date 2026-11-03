<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\Market;
use App\Models\RegistrationStatus;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 286 (note đỏ V 01/11): gia hạn (kéo dài ngày đi) phải check thông số over
 * và không được gây trùng số phòng với booking khác đã đặt.
 * A: phòng 1212 ngày 09-09 -> 09-14; B: phòng 1212 ngày 09-14 -> 09-16. Gia hạn A sang 09-15 => trùng đêm 14.
 */
class ExtendStayConflictTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $suite;
    private int $statusId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDateRollSeeder']);
        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'ext_user']));

        $this->statusId = (int) RegistrationStatus::where('is_availability', 1)->value('booking_status_id');
        $form = RoomForm::first() ?? RoomForm::create(['name' => 'King']);
        $this->suite = RoomClass::create(['code' => 'SUITE', 'name' => 'Suite', 'is_active' => true]);
        foreach (['1212', '1312', '1412'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->suite->id, 'room_form_id' => $form->id, 'floor' => 12, 'status' => 'vacant_ready']);
        }
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        \App\Models\SystemDateRoll::create(['system_date' => '2026-08-29', 'actual_date' => now(), 'shift' => '1', 'username' => 'ext_user']);
    }

    private function booking(string $arrival, string $departure): Booking
    {
        return Booking::create([
            'booking_name' => "BK {$arrival}", 'booking_date' => '2026-08-01', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'status' => Booking::STATUS_RESERVATION, 'registration_status_id' => $this->statusId, 'created_by' => 'ext_user',
        ]);
    }

    private function room(Booking $b, string $arrival, string $departure, ?string $roomNumber): BookingRoom
    {
        return BookingRoom::create([
            'booking_id' => $b->id, 'room_number' => $roomNumber, 'room_class_id' => $this->suite->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 1000000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    /** A giữ 1212 đêm 9..13, header đã rộng tới 15; B giữ 1212 từ đêm 14. */
    private function scenario(): array
    {
        $a = $this->booking('2026-09-09', '2026-09-15');
        $ra = $this->room($a, '2026-09-09', '2026-09-14', '1212');
        $b = $this->booking('2026-09-14', '2026-09-16');
        $this->room($b, '2026-09-14', '2026-09-16', '1212');

        return [$a, $ra];
    }

    public function test_room_info_update_blocks_extending_into_night_held_by_other_booking_for_same_room_number(): void
    {
        [$a, $ra] = $this->scenario();

        $res = $this->putJson("/api/bookings/{$a->id}/rooms/{$ra->id}", ['departure_date' => '2026-09-15']);

        $res->assertStatus(422);
        $this->assertSame('2026-09-14', $ra->fresh()->departure_date->toDateString());
    }

    public function test_quick_update_blocks_extending_into_night_held_by_other_booking_for_same_room_number(): void
    {
        [$a, $ra] = $this->scenario();

        $res = $this->postJson("/api/bookings/{$a->id}/rooms/bulk-update", ['room_ids' => [$ra->id], 'departure_date' => '2026-09-15']);

        $res->assertStatus(422);
        $this->assertSame('2026-09-14', $ra->fresh()->departure_date->toDateString());
    }

    public function test_room_plan_blocks_extending_into_night_held_by_other_booking_for_same_room_number(): void
    {
        [$a, $ra] = $this->scenario();

        $res = $this->putJson("/api/bookings/{$a->id}/rooms/{$ra->id}/room-plan-stay", [
            'arrival_date' => '2026-09-09', 'departure_date' => '2026-09-15',
        ]);

        $res->assertStatus(422);
        $this->assertSame('2026-09-14', $ra->fresh()->departure_date->toDateString());
    }

    public function test_booking_header_sync_blocks_extending_rooms_into_nights_held_by_other_booking(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SyncRoomDateByBookingDate'], ['value' => '1']);
        $x = $this->booking('2026-09-14', '2026-09-17');
        $r1 = $this->room($x, '2026-09-14', '2026-09-17', '1212');
        $r2 = $this->room($x, '2026-09-14', '2026-09-17', '1312');
        $z = $this->booking('2026-09-17', '2026-09-19');
        $this->room($z, '2026-09-17', '2026-09-19', '1212');

        $res = $this->putJson("/api/bookings/{$x->id}", [
            'booking_name' => $x->booking_name, 'arrival_date' => '2026-09-14', 'departure_date' => '2026-09-21', 'num_of_days' => 7,
            'registration_status_id' => $this->statusId,
            'company_id' => (Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]))->id,
            'market_id' => (Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']))->id,
            'customer_source_id' => (CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']))->id,
        ]);

        $res->assertStatus(422);
        $this->assertSame('2026-09-17', $r1->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-17', $r2->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-17', $x->fresh()->departure_date->toDateString());
    }

    public function test_night_audit_extend_stay_blocks_when_room_number_held_by_other_booking(): void
    {
        $a = $this->booking('2026-08-27', '2026-08-29');
        $ra = $this->room($a, '2026-08-27', '2026-08-29', '1212');
        $ra->forceFill(['status' => BookingRoom::STATUS_CHECKED_IN])->save();
        $b = $this->booking('2026-08-29', '2026-09-02');
        $this->room($b, '2026-08-29', '2026-09-02', '1212');

        $res = $this->postJson('/api/night-audit/extend-stay', ['booking_room_id' => (string) $ra->id, 'nights' => 1]);

        $res->assertStatus(422);
        $this->assertSame('2026-08-29', $ra->fresh()->departure_date->toDateString());
    }
    // ------------------------------------------------------------------ Gia hạn ở Đóng ngày (extend-stay)

    private function inhouse(string $arrival, string $departure, ?string $roomNumber): BookingRoom
    {
        $b = $this->booking($arrival, $departure);
        $r = $this->room($b, $arrival, $departure, $roomNumber);
        $r->forceFill(['status' => BookingRoom::STATUS_CHECKED_IN])->save();

        return $r;
    }

    private function extend(BookingRoom $room, int $nights = 1, array $extra = [])
    {
        return $this->postJson('/api/night-audit/extend-stay', ['booking_room_id' => (string) $room->id, 'nights' => $nights] + $extra);
    }

    private function fillOtherRooms(string $from, string $to): void
    {
        foreach (['1312', '1412'] as $number) {
            $this->room($this->booking($from, $to), $from, $to, $number);
        }
    }

    public function test_extend_stay_succeeds_when_nights_are_free(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');

        $this->extend($ra, 2)->assertOk();

        $this->assertSame('2026-08-31', $ra->fresh()->departure_date->toDateString());
        $this->assertSame('2026-08-31', $ra->booking->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_names_the_booking_that_holds_the_room(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        $other = $this->booking('2026-08-29', '2026-09-02');
        $this->room($other, '2026-08-29', '2026-09-02', '1212');

        $res = $this->extend($ra)->assertStatus(422)->assertJsonPath('code', 'room_number_conflict');

        $this->assertStringContainsString('đã được đặt cho đăng ký', $res->json('message'));
        $this->assertStringContainsString((string) ($other->fresh()->booking_code ?: '#' . $other->id), $res->json('message'));
    }

    public function test_extend_stay_blocks_when_only_a_later_night_is_held(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        $other = $this->booking('2026-08-30', '2026-09-02');
        $this->room($other, '2026-08-30', '2026-09-02', '1212');

        $this->extend($ra, 1)->assertOk();
        $this->extend($ra, 1)->assertStatus(422)->assertJsonPath('code', 'room_number_conflict');
        $this->assertSame('2026-08-30', $ra->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_ignores_cancelled_booking_holding_the_room(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        $other = $this->booking('2026-08-29', '2026-09-02');
        $this->room($other, '2026-08-29', '2026-09-02', '1212')->forceFill(['status' => BookingRoom::STATUS_CANCELLED])->save();

        $this->extend($ra)->assertOk();
    }

    public function test_extend_stay_ignores_other_room_of_same_booking(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        $this->room($ra->booking, '2026-08-29', '2026-09-02', '1212');

        $this->extend($ra)->assertOk();
    }

    public function test_extend_stay_blocks_when_room_is_locked(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        \App\Models\RoomLock::create(['room_number' => '1212', 'start_date' => '2026-08-29 00:00:00', 'end_date' => '2026-09-02 00:00:00', 'reason' => 'x', 'status' => 'Active', 'username' => 'ext_user', 'lock_type' => 'OOO', 'is_active' => 1]);

        $this->extend($ra)->assertStatus(422)->assertJsonPath('code', 'room_locked');
        $this->assertSame('2026-08-29', $ra->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_unassigned_room_blocked_when_class_is_full_and_allow_over_is_off(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', null);
        $this->fillOtherRooms('2026-08-29', '2026-09-02');
        $this->room($this->booking('2026-08-29', '2026-09-02'), '2026-08-29', '2026-09-02', null);

        $this->extend($ra)->assertStatus(422)->assertJsonPath('code', 'no_availability');
        $this->assertSame('2026-08-29', $ra->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_asks_confirmation_when_class_is_full_and_allow_over_is_on(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $ra = $this->inhouse('2026-08-27', '2026-08-29', null);
        $this->fillOtherRooms('2026-08-29', '2026-09-02');
        $this->room($this->booking('2026-08-29', '2026-09-02'), '2026-08-29', '2026-09-02', null);

        $this->extend($ra)->assertStatus(409)->assertJsonPath('code', 'overbooking_confirmation_required');
        $this->assertSame('2026-08-29', $ra->fresh()->departure_date->toDateString());

        $res = $this->extend($ra, 1, ['confirm_overbooking' => true])->assertOk();
        $this->assertNotNull($res->json('warning'));
        $this->assertSame('2026-08-30', $ra->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_allow_over_never_bypasses_room_number_conflict(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $ra = $this->inhouse('2026-08-27', '2026-08-29', '1212');
        $this->room($this->booking('2026-08-29', '2026-09-02'), '2026-08-29', '2026-09-02', '1212');

        $this->extend($ra, 1, ['confirm_overbooking' => true])->assertStatus(422);
        $this->assertSame('2026-08-29', $ra->fresh()->departure_date->toDateString());
    }

    public function test_extend_stay_blocks_when_any_extended_night_is_full(): void
    {
        $ra = $this->inhouse('2026-08-27', '2026-08-29', null);
        $this->fillOtherRooms('2026-08-30', '2026-09-02');
        $this->room($this->booking('2026-08-30', '2026-09-02'), '2026-08-30', '2026-09-02', null);

        $this->extend($ra, 1)->assertOk();
        $this->extend($ra, 1)->assertStatus(422)->assertJsonPath('code', 'no_availability');
    }

    // ------------------------------------------------------------------ Room Plan: đêm kín đúng bằng tổng phòng

    public function test_room_plan_blocks_extending_into_a_night_that_is_exactly_full_and_allows_a_night_with_one_free_room(): void
    {
        $a = $this->booking('2026-09-09', '2026-09-15');
        $ra = $this->room($a, '2026-09-09', '2026-09-14', null);
        // Đêm 14: 2 phòng bị giữ -> còn 1 phòng cho A => được. Đêm 15: kín 3/3 (không tính A) -> chặn.
        foreach ([null, null] as $n) {
            $this->room($this->booking('2026-09-14', '2026-09-15'), '2026-09-14', '2026-09-15', $n);
        }
        foreach ([null, null, null] as $n) {
            $this->room($this->booking('2026-09-15', '2026-09-16'), '2026-09-15', '2026-09-16', $n);
        }
        $stay = fn (string $dep) => $this->putJson("/api/bookings/{$a->id}/rooms/{$ra->id}/room-plan-stay", ['arrival_date' => '2026-09-09', 'departure_date' => $dep]);

        $stay('2026-09-15')->assertOk();
        $stay('2026-09-16')->assertStatus(422);
        $this->assertSame('2026-09-15', $ra->fresh()->departure_date->toDateString());
    }
}
