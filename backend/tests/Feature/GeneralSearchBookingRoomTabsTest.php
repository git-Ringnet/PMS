<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 408, 409, 410: Tìm kiếm chung - tab Đăng ký và tab Phòng.
 */
class GeneralSearchBookingRoomTabsTest extends TestCase
{
    use RefreshDatabase;

    private int $classId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'fo_user']));
        DB::table('booking_statuses')->insert([['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'], ['id' => 3, 'name' => 'Cancelled'], ['id' => 4, 'name' => 'No Show']]);
        DB::table('system_date_rolls')->insert(['system_date' => '2026-09-03', 'actual_date' => '2026-09-03', 'shift' => '1', 'username' => 'fo_user', 'created_at' => now(), 'updated_at' => now()]);
        $this->classId = RoomClass::create(['code' => 'SUPTR', 'name' => 'Superior Triple', 'is_active' => true])->id;
        $formId = RoomForm::create(['name' => 'Triple'])->id;
        foreach (['201', '202', '209', '301', '509'] as $number) {
            Room::create(['room_number' => $number, 'room_form_id' => $formId, 'room_class_id' => $this->classId, 'floor' => (int) $number[0], 'status' => 'vacant_ready']);
        }
    }

    private function booking(string $name, string $arrival, string $departure, int $status = Booking::STATUS_RESERVATION, array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => $name, 'booking_date' => '2026-09-01', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'status' => $status, 'created_by' => 'fo_user',
        ], $extra));
    }

    private function room(Booking $booking, array $extra = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'booking_id' => $booking->id, 'room_class_id' => $this->classId,
            'arrival_date' => $booking->arrival_date, 'departure_date' => $booking->departure_date,
            'rate' => 600000, 'adults' => 3, 'status' => BookingRoom::STATUS_BOOKED,
        ], $extra));
    }

    private function bill(BookingRoom $room, string $code, string $date, float $amount, array $extra = []): int
    {
        return DB::table('service_bills')->insertGetId(array_merge([
            'Date' => $date, 'ServiceId' => $code, 'DescriptionServive' => $code, 'Quantity' => 1, 'Amount' => $amount,
            'OpenTime' => '12:00', 'Guest' => 'Guest 1', 'DepartmentId' => 'FO', 'Username' => 'fo_user', 'Edit' => 0, 'Status' => 0, 'RegisterId1' => $room->booking_id, 'RentalRoomId1' => $room->id,
            'created_at' => now(), 'updated_at' => now(),
        ], $extra), 'Ma');
    }

    private function payment(Booking $booking, ?BookingRoom $room, float $amount, string $pack2 = 'DPR', int $editFlag = 0, int $status = Payment::STATUS_PAID): void
    {
        DB::table('payments')->insert([
            'booking_id' => $booking->id, 'booking_room_id' => $room?->id, 'date' => '2026-09-01', 'amount' => $amount, 'pack2' => $pack2,
            'edit_flag' => $editFlag, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function bookingRows(string $query = ''): array
    {
        return collect($this->getJson('/api/general-search?tab=booking&use_date=false&per_page=200' . $query)->assertOk()->json('data.data'))
            ->keyBy('booking_code')->all();
    }

    // ---------------- Dòng 408 ----------------

    public function test_row408_booking_without_rooms_is_listed_in_booking_tab(): void
    {
        $empty = $this->booking('tets tìm kiếm', '2026-09-04', '2026-09-05');
        $this->room($this->booking('test', '2026-09-04', '2026-09-05'));

        $rows = $this->bookingRows();

        $this->assertArrayHasKey($empty->booking_code, $rows);
        $this->assertSame([], $rows[$empty->booking_code]['rooms']);
        $this->assertCount(2, $rows);
    }

    public function test_row408_date_filter_uses_booking_dates(): void
    {
        $empty = $this->booking('BK không phòng', '2026-09-04', '2026-09-05');
        $outside = $this->booking('BK ngoài khoảng', '2026-09-10', '2026-09-12');
        $this->room($outside);

        $rows = $this->bookingRows('&use_date=true&from_date=2026-09-04&to_date=2026-09-04');

        $this->assertArrayHasKey($empty->booking_code, $rows);
        $this->assertArrayNotHasKey($outside->booking_code, $rows);
    }

    public function test_row408_service_only_booking_is_still_hidden(): void
    {
        $serviceOnly = $this->booking('Dịch vụ lẻ', '2026-09-04', '2026-09-05', Booking::STATUS_RESERVATION, ['is_service_only' => true]);

        $this->assertArrayNotHasKey($serviceOnly->booking_code, $this->bookingRows());
    }

    public function test_row408_room_filters_still_require_a_matching_room(): void
    {
        $empty = $this->booking('BK không phòng', '2026-09-04', '2026-09-05');
        $withRoom = $this->booking('BK có phòng', '2026-09-04', '2026-09-05');
        $this->room($withRoom);

        $rows = $this->bookingRows('&room_class_id=' . $this->classId);

        $this->assertArrayNotHasKey($empty->booking_code, $rows);
        $this->assertArrayHasKey($withRoom->booking_code, $rows);
    }

    // ---------------- Dòng 409 ----------------

    public function test_row409_active_booking_hides_cancelled_room_and_counts_babies(): void
    {
        // GAL46: 2 phòng SUPTR, 1 phòng đã hủy, phòng còn lại có 1 em bé.
        $booking = $this->booking('TEST GÁN PHÒNG 2', '2026-09-04', '2026-09-05');
        $active = $this->room($booking, ['room_number' => '509']);
        $this->room($booking, ['status' => BookingRoom::STATUS_CANCELLED]);
        BookingChild::create(['booking_id' => $booking->id, 'booking_room_id' => $active->id, 'full_name' => 'Bé', 'age_group' => 'baby']);

        $row = $this->bookingRows()[$booking->booking_code];

        $this->assertCount(1, $row['rooms']);
        $this->assertSame('SUPTR (1)', $row['original_room_types']);
        $this->assertSame('SUPTR (1)', $row['actual_room_types']);
        $this->assertSame(1, $row['rooms'][0]['babies']);
        $this->assertSame(0, $row['rooms'][0]['children']);
        $this->assertEquals(600000, $row['total_amount']);
    }

    public function test_row409_cancelled_booking_keeps_its_rooms(): void
    {
        $booking = $this->booking('BK đã hủy', '2026-09-04', '2026-09-05', Booking::STATUS_DELETED);
        $this->room($booking, ['status' => BookingRoom::STATUS_CANCELLED]);

        $this->assertCount(1, $this->bookingRows()[$booking->booking_code]['rooms']);
    }

    public function test_row409_total_excludes_services_posted_by_reception(): void
    {
        $booking = $this->booking('BK minibar', '2026-09-04', '2026-09-06');
        $room = $this->room($booking, ['room_number' => '301']);
        // Lễ tân post minibar ở hóa đơn: không có trong setup BK -> không cộng.
        $this->bill($room, 'MB', '2026-09-04', 150000);

        $this->assertEquals(1200000, $this->bookingRows()[$booking->booking_code]['total_amount']);
    }

    public function test_row409_total_counts_setup_services_and_extra_bed_like_booking_screen(): void
    {
        $booking = $this->booking('BK có setup', '2026-09-04', '2026-09-06');
        $room = $this->room($booking, ['extra_bed_qty' => 1, 'extra_bed_rate' => 300000]);
        BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'SPA', 'service_name' => 'Spa', 'service_date' => '2026-09-04', 'quantity' => 2, 'rate' => 100000, 'total_amount' => 200000]);

        // 2 đêm x (600k phòng + 300k giường phụ) + dịch vụ setup 200k.
        $this->assertEquals(2000000, $this->bookingRows()[$booking->booking_code]['total_amount']);
    }

    public function test_row409_past_nights_follow_posted_bills(): void
    {
        // Ngày hệ thống 03/09: đêm 01/09 có bill 550k, đêm 02/09 bill đã xóa (Edit=1) -> 0, đêm 03/09 theo giá BK.
        $booking = $this->booking('BK đang ở', '2026-09-01', '2026-09-04', Booking::STATUS_CHECKIN);
        $room = $this->room($booking, ['room_number' => '209', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->bill($room, 'RM', '2026-09-01', 550000);
        $this->bill($room, 'RM', '2026-09-02', 600000, ['Edit' => 1]);

        $this->assertEquals(1150000, $this->bookingRows()[$booking->booking_code]['total_amount']);
    }

    public function test_row409_deposit_matches_booking_screen(): void
    {
        $booking = $this->booking('BK cọc', '2026-09-04', '2026-09-05');
        $this->room($booking);
        $this->payment($booking, null, 500000);
        $this->payment($booking, null, 200000, 'DPR', 1);      // cọc đã sửa/hủy
        $this->payment($booking, null, 300000, 'PAY');         // thanh toán, không phải cọc

        $this->assertEquals(500000, $this->bookingRows()[$booking->booking_code]['deposit_amount']);
    }

    // ---------------- Dòng 410 ----------------

    private function roomTab(string $query = ''): array
    {
        return $this->getJson('/api/general-search?tab=room&use_date=false&per_page=200' . $query)->assertOk()->json('data');
    }

    public function test_row410_room_tab_hides_cancelled_rooms_of_active_booking(): void
    {
        $booking = $this->booking('test ghi log', '2026-09-09', '2026-09-17');
        $this->room($booking, ['room_number' => '209']);
        $this->room($booking, ['status' => BookingRoom::STATUS_CANCELLED]);

        $group = collect($this->roomTab()['data'])->firstWhere('booking_id', $booking->id);

        $this->assertCount(1, $group['rooms']);
        $this->assertSame('209', $group['rooms'][0]['room_number']);
    }

    public function test_row410_amounts_follow_invoice_screen_not_planned_room_rate(): void
    {
        // GAL22: chưa post gì -> màn hình Hóa đơn Tổng DV = 0, Đã TT = 0.
        $booking = $this->booking('test ghi log', '2026-09-09', '2026-09-17');
        $this->room($booking, ['room_number' => '209', 'rate' => 700000]);

        $group = collect($this->roomTab()['data'])->firstWhere('booking_id', $booking->id);

        $this->assertEquals(0, $group['rooms'][0]['service_total']);
        $this->assertEquals(0, $group['rooms'][0]['paid_total']);
        $this->assertEquals(0, $group['service_total']);
        $this->assertEquals(0, $group['paid_total']);
    }

    public function test_row410_posted_charges_and_payments_per_room_and_booking(): void
    {
        $booking = $this->booking('BK đã post', '2026-09-01', '2026-09-04', Booking::STATUS_CHECKIN);
        $room = $this->room($booking, ['room_number' => '209', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->bill($room, 'RM', '2026-09-01', 700000);
        $this->bill($room, 'RM', '2026-09-02', 700000, ['Edit' => 1]); // bill đã xóa
        $minibar = $this->bill($room, 'MB', '2026-09-02', 50000);
        BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'MB', 'service_name' => 'Minibar', 'service_date' => '2026-09-02', 'quantity' => 1, 'rate' => 50000, 'total_amount' => 50000, 'is_posted' => 1, 'service_bill_id' => $minibar]);
        $this->payment($booking, $room, 300000, 'DPR', 0, Payment::STATUS_PENDING); // cọc phòng
        $this->payment($booking, null, 100000, 'PAY');                              // thanh toán Phiếu Tổng
        DB::table('service_bills')->insert([
            'Date' => '2026-09-02', 'ServiceId' => 'LD', 'DescriptionServive' => 'Giặt là', 'Quantity' => 1, 'Amount' => 80000,
            'OpenTime' => '12:00', 'Guest' => 'Guest 1', 'DepartmentId' => 'FO', 'Username' => 'fo_user', 'Edit' => 0, 'Status' => 0, 'RegisterId1' => $booking->id, 'created_at' => now(), 'updated_at' => now(),
        ]); // dịch vụ Phiếu Tổng

        $group = collect($this->roomTab()['data'])->firstWhere('booking_id', $booking->id);

        $this->assertEquals(750000, $group['rooms'][0]['service_total']);
        $this->assertEquals(300000, $group['rooms'][0]['paid_total']);
        $this->assertEquals(830000, $group['service_total']);
        $this->assertEquals(400000, $group['paid_total']);
    }

    public function test_row410_result_counts_bookings_and_rooms(): void
    {
        $first = $this->booking('BK 1', '2026-09-09', '2026-09-17');
        $this->room($first, ['room_number' => '201']);
        $this->room($first, ['room_number' => '202']);
        $this->room($first, ['status' => BookingRoom::STATUS_CANCELLED]);
        $this->room($this->booking('BK 2', '2026-09-09', '2026-09-10'), ['room_number' => '301']);

        $data = $this->roomTab();

        $this->assertSame(2, $data['total']);
        $this->assertSame(3, $data['room_total']);
    }
}
