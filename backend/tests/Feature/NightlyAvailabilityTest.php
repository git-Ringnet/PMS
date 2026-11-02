<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\RegistrationStatus;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phòng trống khi đổi ngày phòng thuê (Cập nhật nhanh phòng / Thông tin phòng):
 *  - giai đoạn nhiều đêm tính theo đêm thấp nhất, không cộng dồn các booking chạm vào giai đoạn;
 *  - chỉ các đêm MỚI thêm vào cần thêm 1 phòng; rút ngắn / giữ nguyên không bao giờ bị báo over.
 *
 * Loại Suite có 3 phòng. Dữ liệu tránh để booking khác kết thúc đúng vào đêm đang kiểm tra
 * (SQLite so sánh chuỗi ngày/giờ khác MySQL ở đúng biên đó).
 */
class NightlyAvailabilityTest extends TestCase
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
        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'av_user']));

        $this->statusId = (int) RegistrationStatus::where('is_availability', 1)->value('booking_status_id');
        $form = RoomForm::first() ?? RoomForm::create(['name' => 'King']);
        $this->suite = RoomClass::create(['code' => 'SUITE', 'name' => 'Suite', 'is_active' => true]);
        foreach (['1212', '1312', '1412'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->suite->id, 'room_form_id' => $form->id, 'floor' => 12, 'status' => 'vacant_ready']);
        }
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        \App\Models\SystemDateRoll::create(['system_date' => '2026-08-29', 'actual_date' => now(), 'shift' => '1', 'username' => 'av_user']);
    }

    private function stay(string $arrival, string $departure, ?string $roomNumber = null, ?int $statusId = null): BookingRoom
    {
        $booking = Booking::create([
            'booking_name' => "BK {$arrival}", 'booking_date' => '2026-08-01', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'status' => Booking::STATUS_RESERVATION, 'registration_status_id' => $statusId ?? $this->statusId, 'created_by' => 'av_user',
        ]);

        return BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => $roomNumber, 'room_class_id' => $this->suite->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 1000000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    /** Lấp đầy cả 3 phòng Suite trong [from, to) bằng 3 booking khác. */
    private function fillAll(string $from, string $to): void
    {
        foreach (['1212', '1412', null] as $number) {
            $this->stay($from, $to, $number);
        }
    }

    private function bulk(BookingRoom $room, array $fields)
    {
        return $this->postJson("/api/bookings/{$room->booking_id}/rooms/bulk-update", ['room_ids' => [$room->id]] + $fields);
    }

    // ------------------------------------------------------------------ getAvailability (service)

    public function test_range_availability_is_the_lowest_night_not_the_sum_of_overlapping_bookings(): void
    {
        $this->stay('2026-08-30', '2026-09-01');
        $this->stay('2026-09-03', '2026-09-05');
        $this->stay('2026-09-06', '2026-09-08');
        $this->stay('2026-08-31', '2026-09-05');
        $service = app(RoomAvailabilityService::class);

        $this->assertSame(1, $service->getAvailability($this->suite->id, '2026-08-30', '2026-09-08'));
        $this->assertSame(3, $service->getAvailability($this->suite->id, '2026-09-10', '2026-09-13'));
    }

    public function test_cancelled_deleted_and_non_availability_bookings_do_not_hold_rooms(): void
    {
        $service = app(RoomAvailabilityService::class);
        $cancelled = $this->stay('2026-09-10', '2026-09-14', '1212');
        $cancelled->update(['status' => BookingRoom::STATUS_CANCELLED]);
        $deleted = $this->stay('2026-09-10', '2026-09-14', '1312');
        $deleted->booking->update(['status' => Booking::STATUS_DELETED]);
        $waiting = RegistrationStatus::where('is_availability', 0)->value('booking_status_id');
        if ($waiting !== null) {
            $this->stay('2026-09-10', '2026-09-14', '1412', (int) $waiting);
        }

        $this->assertSame(3, $service->getAvailability($this->suite->id, '2026-09-10', '2026-09-14'));
    }

    public function test_locked_room_reduces_capacity_for_the_nights_it_covers(): void
    {
        RoomLock::create(['room_number' => '1212', 'start_date' => '2026-09-10 00:00:00', 'end_date' => '2026-09-13 00:00:00', 'reason' => 'x', 'status' => 'Active', 'username' => 'av_user', 'lock_type' => 'OOO', 'is_active' => 1]);
        $service = app(RoomAvailabilityService::class);

        $this->assertSame(2, $service->getAvailability($this->suite->id, '2026-09-10', '2026-09-12'));
        $this->assertSame(3, $service->getAvailability($this->suite->id, '2026-09-20', '2026-09-22'));
    }

    public function test_added_nights_helper_ignores_nights_the_room_already_holds(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-04', '1312');
        $this->fillAll('2026-09-02', '2026-09-03'); // đêm 2/9 đã đầy kể cả phòng mình giữ
        $service = app(RoomAvailabilityService::class);

        // rút ngắn / giữ nguyên: không có đêm mới
        $this->assertNull($service->getAvailabilityAfterAddingNights($this->suite->id, '2026-08-30', '2026-09-03', '2026-08-30', '2026-09-04', $mine->id));
        $this->assertNull($service->getAvailabilityAfterAddingNights($this->suite->id, '2026-08-30', '2026-09-04', '2026-08-30', '2026-09-04', $mine->id));
        // kéo dài sang đêm 4/9 còn trống cả 3 -> còn dư 2 phòng sau khi thêm
        $this->assertSame(2, $service->getAvailabilityAfterAddingNights($this->suite->id, '2026-08-30', '2026-09-05', '2026-08-30', '2026-09-04', $mine->id));
    }

    // ------------------------------------------------------------------ Cập nhật nhanh phòng (bulk-update)

    public function test_shortening_checkout_is_never_blocked_even_when_the_period_is_oversold(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-09', '1312');
        $this->fillAll('2026-09-02', '2026-09-05');     // các đêm này đã vượt tổng phòng (đã bán quá từ trước)
        $this->stay('2026-09-06', '2026-09-08');
        $this->stay('2026-09-02', '2026-09-03');

        foreach (['2026-09-07', '2026-09-06', '2026-09-03'] as $departure) {
            $this->bulk($mine, ['departure_date' => $departure])->assertOk();
            $this->assertSame($departure, $mine->fresh()->departure_date->toDateString());
            $mine->update(['departure_date' => '2026-09-09']);
        }
    }

    public function test_shortening_from_the_arrival_side_is_not_blocked(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-06', '1312');
        $this->fillAll('2026-08-30', '2026-09-02');

        $this->bulk($mine, ['arrival_date' => '2026-08-31', 'departure_date' => '2026-09-06'])->assertOk();
        $this->assertSame('2026-08-31', $mine->fresh()->arrival_date->toDateString());
    }

    public function test_unchanged_period_is_not_checked(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->fillAll('2026-09-01', '2026-09-05');

        $this->bulk($mine, ['departure_date' => '2026-09-05', 'note' => 'giữ nguyên'])->assertOk();
    }

    public function test_extending_into_a_night_with_free_rooms_is_allowed(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->stay('2026-09-06', '2026-09-08', '1212'); // đêm 5-6/9 còn trống cả 3

        $this->bulk($mine, ['departure_date' => '2026-09-07'])->assertOk();
        $this->assertSame('2026-09-07', $mine->fresh()->departure_date->toDateString());
    }

    public function test_extending_into_a_night_that_is_exactly_full_is_blocked(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->fillAll('2026-09-05', '2026-09-08'); // 3 phòng khác kín các đêm 5-7/9: phòng mình sẽ là phòng thứ 4

        $response = $this->bulk($mine, ['departure_date' => '2026-09-07']);

        $response->assertStatus(422);
        $this->assertStringContainsString('Không còn phòng trống cho loại phòng Suite', (string) $response->json('message'));
        $this->assertSame('2026-09-05', $mine->fresh()->departure_date->toDateString());
    }

    public function test_extending_blocked_when_only_one_of_the_added_nights_is_full(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->fillAll('2026-09-07', '2026-09-09'); // đêm 5,6/9 trống; đêm 7,8/9 kín

        $this->bulk($mine, ['departure_date' => '2026-09-09'])->assertStatus(422);
        $this->assertSame('2026-09-05', $mine->fresh()->departure_date->toDateString());

        // chỉ kéo tới hết đêm còn trống thì được
        $this->bulk($mine, ['departure_date' => '2026-09-07'])->assertOk();
    }

    public function test_extending_arrival_earlier_into_a_full_night_is_blocked(): void
    {
        $mine = $this->stay('2026-09-03', '2026-09-06', '1312');
        $this->fillAll('2026-09-01', '2026-09-03');

        $this->bulk($mine, ['arrival_date' => '2026-09-01', 'departure_date' => '2026-09-06'])->assertStatus(422);
        $this->assertSame('2026-09-03', $mine->fresh()->arrival_date->toDateString());
    }

    public function test_extending_into_full_night_asks_for_confirmation_when_allow_over_is_on(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->fillAll('2026-09-05', '2026-09-08');

        $response = $this->bulk($mine, ['departure_date' => '2026-09-07']);
        $response->assertOk();
        $this->assertSame('OVERBOOKING_WARNING', $response->json('code'));
        $this->assertSame('2026-09-05', $mine->fresh()->departure_date->toDateString());

        $this->bulk($mine, ['departure_date' => '2026-09-07', 'confirm_overbooking' => true])->assertOk();
        $this->assertSame('2026-09-07', $mine->fresh()->departure_date->toDateString());
    }

    // ------------------------------------------------------------------ Room map -> Thông tin phòng (PUT)

    public function test_room_info_update_extend_blocked_and_shorten_allowed(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        $this->fillAll('2026-09-05', '2026-09-08');

        $this->putJson("/api/bookings/{$mine->booking_id}/rooms/{$mine->id}", ['departure_date' => '2026-09-07'])->assertStatus(422);
        $this->assertSame('2026-09-05', $mine->fresh()->departure_date->toDateString());

        $this->putJson("/api/bookings/{$mine->booking_id}/rooms/{$mine->id}", ['departure_date' => '2026-09-03'])->assertOk();
        $this->assertSame('2026-09-03', $mine->fresh()->departure_date->toDateString());
    }

    public function test_sibling_room_of_the_same_booking_is_counted_once(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        BookingRoom::create([
            'booking_id' => $mine->booking_id, 'room_number' => '1212', 'room_class_id' => $this->suite->id,
            'arrival_date' => '2026-08-30', 'departure_date' => '2026-09-05', 'rate' => 1000000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);

        // 2 phòng của booking này + chính phòng mình = còn dư 1 phòng cho đêm 5/9
        $this->bulk($mine, ['departure_date' => '2026-09-07'])->assertOk();
    }
}
