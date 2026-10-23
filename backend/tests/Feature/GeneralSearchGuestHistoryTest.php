<?php

namespace Tests\Feature;

use App\Models\Booking;
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
 * Dòng 355: Tìm kiếm chung - tab Khách: chi tiết khách và lịch sử lưu trú.
 */
class GeneralSearchGuestHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'fo_user']));
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'], ['id' => 3, 'name' => 'Cancelled'], ['id' => 4, 'name' => 'No Show']]);

        $class = RoomClass::create(['code' => 'DLX', 'name' => 'Deluxe Twin', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Twin']);
        foreach (['104', '205', '301'] as $number) {
            Room::create(['room_number' => $number, 'room_form_id' => $form->id, 'room_class_id' => $class->id, 'floor' => (int) $number[0], 'status' => 'vacant_ready']);
        }

        $this->guest = Guest::create([
            'full_name' => 'Nguyễn Văn Khách', 'phone' => '0909123456', 'email' => 'khach@example.com',
            'nationality_code' => 'VNM', 'id_number' => '079123456789', 'dob' => '1990-05-20',
        ]);

        // Lần 1: đã trả phòng (2 đêm), lần 2: đang ở (3 đêm), lần 3: đã hủy (không tính)
        $this->stay('2026-08-01', '2026-08-03', '104', BookingRoom::STATUS_CHECKED_OUT, 'Đoàn tháng 8');
        $this->stay('2026-09-10', '2026-09-13', '205', BookingRoom::STATUS_CHECKED_IN, 'Khách lẻ tháng 9');
        $this->stay('2026-10-01', '2026-10-02', '301', BookingRoom::STATUS_CANCELLED, 'Booking hủy');
    }

    private function stay(string $arrival, string $departure, string $roomNumber, int $status, string $name): void
    {
        $booking = Booking::create([
            'booking_name' => $name, 'booking_date' => $arrival, 'arrival_date' => $arrival, 'departure_date' => $departure,
            'status' => $status === BookingRoom::STATUS_CHECKED_OUT ? 2 : ($status === BookingRoom::STATUS_CHECKED_IN ? 1 : 0),
            'created_by' => 'fo_user',
        ]);
        $room = BookingRoom::create([
            'booking_id' => $booking->id, 'room_number' => $roomNumber, 'room_class_id' => RoomClass::first()->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 650000, 'status' => $status,
        ]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $this->guest->id, 'status' => 0, 'is_primary' => true]);
    }

    public function test_guest_tab_rows_include_guest_id(): void
    {
        $rows = $this->getJson('/api/general-search?tab=guest&use_date=false&search=Nguyễn')->assertOk()->json('data.data');

        $this->assertNotEmpty($rows);
        $this->assertSame($this->guest->id, $rows[0]['guest_id']);
    }

    public function test_history_returns_guest_details_and_all_stays_newest_first(): void
    {
        $data = $this->getJson("/api/general-search/guests/{$this->guest->id}/history")->assertOk()->json('data');

        $this->assertSame('Nguyễn Văn Khách', $data['guest']['full_name']);
        $this->assertSame('0909123456', $data['guest']['phone']);
        $this->assertSame('1990-05-20', $data['guest']['dob']);

        $this->assertSame(['301', '205', '104'], array_column($data['stays'], 'room_number'));
        $this->assertSame(['Đã hủy', 'Đang ở', 'Đã trả phòng'], array_column($data['stays'], 'status_label'));
        $this->assertSame('Deluxe Twin', $data['stays'][1]['room_class']);
        $this->assertSame(3, $data['stays'][1]['nights']);
        $this->assertSame('Khách lẻ tháng 9', $data['stays'][1]['booking_name']);
    }

    public function test_summary_excludes_cancelled_stays(): void
    {
        $summary = $this->getJson("/api/general-search/guests/{$this->guest->id}/history")->assertOk()->json('data.summary');

        $this->assertSame(2, $summary['total_bookings']);
        $this->assertSame(5, $summary['total_nights']);
        $this->assertSame('2026-09-10', $summary['last_arrival_date']);
    }

    public function test_unknown_guest_returns_404(): void
    {
        $this->getJson('/api/general-search/guests/K999999999/history')->assertStatus(404);
    }

    public function test_booking_and_room_tabs_with_checked_out_status_do_not_error(): void
    {
        foreach (['booking', 'room', 'guest'] as $tab) {
            $this->getJson("/api/general-search?tab={$tab}&use_date=true&from_date=2026-08-01&to_date=2026-08-31&status=2")
                ->assertOk();
        }
    }
}
