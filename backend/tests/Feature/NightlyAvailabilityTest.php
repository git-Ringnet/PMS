<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\RegistrationStatus;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phòng trống của một giai đoạn nhiều đêm = số phòng trống thấp nhất trong từng đêm,
 * và chỉ rút ngắn giai đoạn thì không bị báo "over".
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
        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => '0']);
        // Ngày hệ thống trước giai đoạn thử để không vướng quy tắc ngày quá khứ
        \App\Models\SystemDateRoll::create(['system_date' => '2026-08-29', 'actual_date' => now(), 'shift' => '1', 'username' => 'av_user']);
    }

    private function stay(string $arrival, string $departure, ?string $roomNumber = null): BookingRoom
    {
        $booking = Booking::create([
            'booking_name' => "BK {$arrival}", 'booking_date' => '2026-08-01', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'status' => Booking::STATUS_RESERVATION, 'registration_status_id' => $this->statusId, 'created_by' => 'av_user',
        ]);

        return BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => $roomNumber, 'room_class_id' => $this->suite->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 1000000, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    public function test_range_availability_is_the_lowest_night_not_the_sum_of_overlapping_bookings(): void
    {
        // 3 phòng Suite. Mỗi đêm chỉ có tối đa 2 phòng bận, nhưng 4 booking cùng chạm vào giai đoạn.
        $this->stay('2026-08-30', '2026-09-01');
        $this->stay('2026-09-03', '2026-09-05');
        $this->stay('2026-09-06', '2026-09-08');
        $this->stay('2026-08-31', '2026-09-05');

        $service = app(RoomAvailabilityService::class);

        // Đêm đông nhất còn đúng 1 phòng (cộng dồn theo booking sẽ ra -1)
        $this->assertSame(1, $service->getAvailability($this->suite->id, '2026-08-30', '2026-09-08'));
        // Giai đoạn không có booking nào: trống cả 3
        $this->assertSame(3, $service->getAvailability($this->suite->id, '2026-09-10', '2026-09-13'));
    }

    public function test_quick_update_shortening_checkout_is_not_blocked_by_over(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-09', '1312');
        // Hai phòng khác của loại Suite trùng gần hết giai đoạn: phòng đã ở sát giới hạn (AV = 0 các đêm đó)
        $this->stay('2026-08-30', '2026-09-09', '1212');
        $this->stay('2026-08-30', '2026-09-09', '1412');
        // Dư thêm một booking đêm 07/09 làm giai đoạn 30/8-7/9 bị "âm" nếu cộng dồn
        $this->stay('2026-09-06', '2026-09-08');
        $this->stay('2026-09-02', '2026-09-03');

        $payload = ['room_ids' => [$mine->id], 'departure_date' => '2026-09-07'];

        $response = $this->postJson("/api/bookings/{$mine->booking_id}/rooms/bulk-update", $payload);

        // Rút ngắn từ 09/09 xuống 07/09: không được lỗi "Không còn phòng trống"
        $this->assertStringNotContainsString('Không còn phòng trống', (string) $response->json('message'));
        $this->assertSame('2026-09-07', $mine->fresh()->departure_date->toDateString());
    }

    public function test_quick_update_extending_into_a_full_night_is_still_blocked(): void
    {
        $mine = $this->stay('2026-08-30', '2026-09-05', '1312');
        foreach (['1212', '1412', null, null] as $number) {
            $this->stay('2026-09-05', '2026-09-08', $number);
        }

        $response = $this->postJson("/api/bookings/{$mine->booking_id}/rooms/bulk-update", ['room_ids' => [$mine->id], 'departure_date' => '2026-09-07']);

        $response->assertStatus(422);
        $this->assertStringContainsString('Không còn phòng trống cho loại phòng Suite', (string) $response->json('message'));
        $this->assertSame('2026-09-05', $mine->fresh()->departure_date->toDateString());
    }
}
