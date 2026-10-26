<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancelLog;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 451: lọc "Tình trạng lưu trú" ở 3 tab Tìm kiếm chung theo nghiệp vụ Link Hotel
 * (sp_041 / sp_043). Ngày hệ thống 10/09/2026.
 */
class GeneralSearchStayStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $classId;
    private int $userId;
    /** @var array<string, Booking> */
    private array $bookings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['username' => 'fo_user']);
        $this->userId = $user->id;
        $this->actingAs($user);
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'], ['id' => 3, 'name' => 'Cancelled'], ['id' => 4, 'name' => 'No Show'], ['id' => 100, 'name' => 'Moved']]);
        DB::table('system_date_rolls')->insert(['system_date' => '2026-09-10', 'actual_date' => '2026-09-10', 'shift' => '1', 'username' => 'fo_user', 'created_at' => now(), 'updated_at' => now()]);
        $this->classId = RoomClass::create(['code' => 'SUPD', 'name' => 'Superior Double', 'is_active' => true])->id;
        $formId = RoomForm::create(['name' => 'Double'])->id;
        foreach (['101', '102', '103', '104', '105', '106', '107'] as $number) {
            Room::create(['room_number' => $number, 'room_form_id' => $formId, 'room_class_id' => $this->classId, 'floor' => 1, 'status' => 'vacant_ready']);
        }

        // 101: sắp đến hôm nay      102: sắp đến 15/09        103: đang ở, đi hôm nay
        // 104: đang ở, đi 14/09     105: đã trả 05-07/09      106: đã hủy ngày 09/09 (BK đang hoạt động)
        // 107: đã chuyển phòng (100)
        $this->room('A', '101', '2026-09-10', '2026-09-12', BookingRoom::STATUS_BOOKED);
        $this->room('A', '102', '2026-09-15', '2026-09-16', BookingRoom::STATUS_BOOKED);
        $this->room('B', '103', '2026-09-08', '2026-09-10', BookingRoom::STATUS_CHECKED_IN);
        $this->room('B', '104', '2026-09-09', '2026-09-14', BookingRoom::STATUS_CHECKED_IN);
        $this->room('C', '105', '2026-09-05', '2026-09-07', BookingRoom::STATUS_CHECKED_OUT);
        $cancelled = $this->room('A', '106', '2026-09-11', '2026-09-12', BookingRoom::STATUS_CANCELLED);
        BookingCancelLog::create([
            'cancel_type' => 'room', 'booking_room_id' => $cancelled->id, 'cancelled_by_user_id' => $this->userId,
            'cancelled_by_username' => 'fo_user', 'cancelled_at' => '2026-09-09 10:00:00',
        ]);
        $this->room('C', '107', '2026-09-05', '2026-09-07', BookingRoom::STATUS_MOVED);
    }

    private function room(string $bookingKey, string $number, string $arrival, string $departure, int $status): BookingRoom
    {
        $booking = $this->bookings[$bookingKey] ??= Booking::create([
            'booking_name' => "BK {$bookingKey}", 'booking_date' => '2026-09-01', 'arrival_date' => '2026-09-05', 'departure_date' => '2026-09-16',
            'status' => ['A' => 0, 'B' => 1, 'C' => 2][$bookingKey], 'created_by' => 'fo_user',
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => $number, 'room_class_id' => $this->classId,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 500000, 'status' => $status,
        ]);
        $guest = Guest::create(['full_name' => "Khách {$number}"]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'status' => min($status, 4), 'is_primary' => true]);

        return $room;
    }

    private function query(string $tab, string $stayStatus, ?string $from = null, ?string $to = null): array
    {
        $dates = $from ? "&use_date=true&from_date={$from}&to_date=" . ($to ?? $from) : '&use_date=false';
        return $this->getJson("/api/general-search?tab={$tab}&stay_status={$stayStatus}&per_page=200{$dates}")->assertOk()->json('data');
    }

    private function roomNumbers(string $stayStatus, ?string $from = null, ?string $to = null): array
    {
        $numbers = collect($this->query('room', $stayStatus, $from, $to)['data'])->flatMap(fn ($group) => array_column($group['rooms'], 'room_number'))->sort()->values()->all();
        return array_map('strval', $numbers);
    }

    private function guestRooms(string $stayStatus, ?string $from = null, ?string $to = null): array
    {
        return collect($this->query('guest', $stayStatus, $from, $to)['data'])->pluck('room_number')->map(fn ($n) => (string) $n)->sort()->values()->all();
    }

    private function bookingNames(string $stayStatus, ?string $from = null, ?string $to = null): array
    {
        return collect($this->query('booking', $stayStatus, $from, $to)['data'])->pluck('booking_name')->sort()->values()->all();
    }

    // ---------- Không bật ngày ----------

    public function test_arrival_without_dates_lists_rooms_not_checked_in(): void
    {
        $this->assertSame(['101', '102'], $this->roomNumbers('arrival'));
    }

    public function test_departure_without_dates_lists_in_house_rooms_not_checked_out(): void
    {
        $this->assertSame(['103', '104'], $this->roomNumbers('departure'));
    }

    public function test_checkin_without_dates_lists_checked_in_and_checked_out_rooms(): void
    {
        // Phòng đã chuyển (100) cũng thuộc Đã đến nhưng tab Phòng không hiển thị dòng phòng chuyển.
        $this->assertSame(['103', '104', '105'], $this->roomNumbers('checkin'));
    }

    public function test_checkout_without_dates_lists_checked_out_rooms(): void
    {
        $this->assertSame(['105'], $this->roomNumbers('checkout'));
    }

    public function test_inhouse_without_dates_lists_rooms_staying_now(): void
    {
        $this->assertSame(['103', '104'], $this->roomNumbers('inhouse'));
    }

    public function test_cancel_lists_cancelled_rooms_even_of_an_active_booking(): void
    {
        $this->assertSame(['106'], $this->roomNumbers('cancel'));
    }

    public function test_all_statuses_keeps_previous_behaviour(): void
    {
        // Không chọn tình trạng: phòng hủy của BK đang hoạt động vẫn ẩn (dòng 410).
        $this->assertSame(['101', '102', '103', '104', '105'], $this->roomNumbers(''));
    }

    // ---------- Bật ngày ----------

    public function test_arrival_with_date_only_lists_rooms_arriving_that_day(): void
    {
        $this->assertSame(['101'], $this->roomNumbers('arrival', '2026-09-10'));
        $this->assertSame(['101', '102'], $this->roomNumbers('arrival', '2026-09-10', '2026-09-15'));
    }

    public function test_departure_with_date_lists_rooms_leaving_in_range(): void
    {
        $this->assertSame(['103'], $this->roomNumbers('departure', '2026-09-10'));
        $this->assertSame(['101', '103', '104'], $this->roomNumbers('departure', '2026-09-10', '2026-09-14'));
    }

    public function test_checkin_with_date_lists_rooms_checked_in_in_range(): void
    {
        $this->assertSame(['104'], $this->roomNumbers('checkin', '2026-09-09'));
        $this->assertSame(['103', '104', '105'], $this->roomNumbers('checkin', '2026-09-05', '2026-09-09'));
    }

    public function test_checkout_with_date_lists_rooms_checked_out_in_range(): void
    {
        $this->assertSame(['105'], $this->roomNumbers('checkout', '2026-09-07'));
        $this->assertSame([], $this->roomNumbers('checkout', '2026-09-10'));
    }

    public function test_inhouse_on_system_date_lists_rooms_staying_now(): void
    {
        $this->assertSame(['103', '104'], $this->roomNumbers('inhouse', '2026-09-10'));
    }

    public function test_inhouse_on_a_past_date_lists_rooms_that_stayed_that_night(): void
    {
        // Đêm 06/09: phòng 105 (đã trả) ở 05-07/09.
        $this->assertSame(['105'], $this->roomNumbers('inhouse', '2026-09-06'));
        // Đêm 08/09: phòng 103 đang ở từ 08/09.
        $this->assertSame(['103'], $this->roomNumbers('inhouse', '2026-09-08'));
    }

    public function test_cancel_with_date_uses_cancellation_date(): void
    {
        $this->assertSame(['106'], $this->roomNumbers('cancel', '2026-09-09'));
        $this->assertSame([], $this->roomNumbers('cancel', '2026-09-10'));
    }

    public function test_room_total_counts_only_filtered_rooms(): void
    {
        $this->assertSame(2, $this->query('room', 'arrival')['room_total']);
    }

    // ---------- Tab Đăng ký và tab Khách ----------

    public function test_booking_tab_lists_bookings_having_a_matching_room(): void
    {
        $this->assertSame(['BK A'], $this->bookingNames('arrival'));
        $this->assertSame(['BK B'], $this->bookingNames('departure', '2026-09-10'));
        $this->assertSame(['BK B', 'BK C'], $this->bookingNames('checkin'));
        $this->assertSame(['BK A'], $this->bookingNames('cancel', '2026-09-09'));
    }

    public function test_booking_tab_date_filter_follows_the_room_status_not_booking_dates(): void
    {
        // Ngày 15/09 nằm trong ngày của cả 3 booking, nhưng chỉ BK A có phòng đến ngày 15/09.
        $this->assertSame(['BK A'], $this->bookingNames('arrival', '2026-09-15'));
    }

    public function test_guest_tab_lists_guests_of_matching_rooms(): void
    {
        $this->assertSame(['101', '102'], $this->guestRooms('arrival'));
        $this->assertSame(['103', '104'], $this->guestRooms('inhouse', '2026-09-10'));
        $this->assertSame(['105'], $this->guestRooms('checkout', '2026-09-07'));
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->getJson('/api/general-search?tab=room&stay_status=abc')->assertStatus(422);
    }
}
