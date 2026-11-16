<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\RoomClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checklist Lễ tân - Phiếu ăn sáng (TC_LT_19), dòng 265/266/269 - task 505.
 */
class BreakfastListRow505Test extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        $form = \App\Models\RoomForm::create(['name' => 'Standard']);
        foreach (['101', '102', '1212', '412', '301'] as $number) {
            \App\Models\Room::create([
                'room_number' => $number, 'room_form_id' => $form->id, 'room_class_id' => $this->roomClass->id,
                'floor' => 1, 'status' => 'available', 'room_status_code' => 'vacant_clean',
            ]);
        }
    }

    private function booking(string $arrival, string $departure): Booking
    {
        return Booking::create([
            'booking_name' => 'Khach an sang', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'num_of_days' => 1, 'booking_date' => $arrival, 'created_by' => 'test',
        ]);
    }

    private function room(Booking $booking, string $number, int $status, string $arrival, string $departure, array $extra = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id, 'room_number' => $number,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'rate' => 500000, 'adults' => 2,
            'status' => $status, 'breakfast' => 1,
        ], $extra));
    }

    private function list(string $from, string $to, string $dateType): array
    {
        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/breakfast/list?' . http_build_query(['from_date' => $from, 'to_date' => $to, 'date_type' => $dateType, 'show_type' => 1]));
        $response->assertSuccessful();

        return collect($response->json('data'))->map(fn ($r) => $r['room_number'] . '|' . $r['target_date'])->sort()->values()->all();
    }

    public function test_row_505_arrival_tab_shows_only_the_live_room_after_same_day_move(): void
    {
        $booking = $this->booking('2026-09-05', '2026-09-08');
        // Nhận phòng 101 rồi chuyển ngay sang 102 trong ngày 05/09.
        $old = $this->room($booking, '101', 100, '2026-09-05', '2026-09-05', ['actual_arrival_date' => '2026-09-05']);
        $new = $this->room($booking, '102', 1, '2026-09-05', '2026-09-08', ['actual_arrival_date' => '2026-09-05']);
        $old->update(['move_room' => $new->id]);

        $this->assertSame(['102|2026-09-05'], $this->list('2026-09-04', '2026-09-06', 'arrival'));
    }

    public function test_row_505_breakfast_tab_keeps_the_old_room_for_nights_before_the_move(): void
    {
        $booking = $this->booking('2026-08-31', '2026-09-07');
        // Phòng 1212 ở 31/8 - 5/9 rồi chuyển sang 412 (5/9 - 7/9).
        $old = $this->room($booking, '1212', 100, '2026-08-31', '2026-09-05', ['actual_arrival_date' => '2026-08-31']);
        $new = $this->room($booking, '412', 1, '2026-09-05', '2026-09-07', ['actual_arrival_date' => '2026-08-31']);
        $old->update(['move_room' => $new->id]);

        // Ngày ăn sáng 05/09 vẫn thuộc phòng cũ (để in lại phiếu ngày 5), 06 và 07/09 thuộc phòng mới.
        $this->assertSame(
            ['1212|2026-09-05', '412|2026-09-06', '412|2026-09-07'],
            $this->list('2026-09-05', '2026-09-07', 'breakfast')
        );
        $this->assertContains('1212|2026-09-01', $this->list('2026-09-01', '2026-09-01', 'breakfast'));

        // Tab ngày đến chỉ hiện phòng mới với giai đoạn 5/9 - 7/9, không hiện phòng cũ.
        $this->assertSame(['412|2026-09-05'], $this->list('2026-08-31', '2026-09-06', 'arrival'));
    }

    public function test_row_505_breakfast_tab_shows_reserved_future_rooms(): void
    {
        $booking = $this->booking('2026-09-06', '2026-09-10');
        $this->room($booking, '301', BookingRoom::STATUS_BOOKED, '2026-09-06', '2026-09-10');

        // Ngày hệ thống 5/9, xem ngày ăn sáng 8/9: phải thấy phòng của booking chưa nhận phòng.
        $this->assertSame(['301|2026-09-08'], $this->list('2026-09-08', '2026-09-08', 'breakfast'));
    }
}
