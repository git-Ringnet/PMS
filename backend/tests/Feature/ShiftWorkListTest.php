<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\LateCheckin;
use App\Models\NoshowLog;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftWorkListTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['username' => 'shift_user']));

        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 4, 'name' => 'No Show'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-09-02',
            'actual_date' => '2026-09-02',
            'shift' => '1',
            'username' => 'shift_user',
        ]);

        $this->class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Standard']);
        foreach (['101', '606', '1105', '705'] as $number) {
            Room::create([
                'room_number' => $number,
                'room_form_id' => $form->id,
                'room_class_id' => $this->class->id,
                'floor' => 1,
                'status' => 'vacant_ready',
            ]);
        }
    }

    private function makeBooking(string $name): Booking
    {
        return Booking::create([
            'booking_name' => $name,
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-02',
            'departure_date' => '2026-09-03',
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => 'shift_user',
        ]);
    }

    private function makeRoom(Booking $booking, int $status, array $extra = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->class->id,
            'arrival_date' => '2026-09-02',
            'departure_date' => '2026-09-03',
            'rate' => 540000,
            'status' => $status,
        ], $extra));
    }

    private function bill(array $attrs): ServiceBill
    {
        return ServiceBill::create(array_merge([
            'Date' => '2026-09-02',
            'OpenTime' => '10:00:00',
            'Guest' => 'Walkin Guest',
            'DepartmentId' => 1,
            'Username' => 'shift_user',
            'ServiceId' => 'RM',
            'DescriptionServive' => 'Tiền phòng',
            'Quantity' => 1,
            'Amount' => 0,
            'Edit' => 0,
            'Status' => 1,
        ], $attrs));
    }

    // Dòng 356 + 362
    public function test_noshow_list_zeroes_no_charge_rows_and_includes_late_checkin_group(): void
    {
        $booking = $this->makeBooking('Noshow BK');

        $noCharge = $this->makeRoom($booking, BookingRoom::STATUS_NOSHOW, ['room_number' => '606', 'total_rate' => 1080000]);
        NoshowLog::create([
            'booking_room_id' => $noCharge->id, 'noshow_date' => '2026-09-02 10:00:00', 'noshow_time' => '10:15',
            'reason' => 'NightAudit No Show No Charge - Khách không đến', 'status' => 4, 'username' => 'shift_user', 'shift' => '1',
        ]);

        $charged = $this->makeRoom($booking, BookingRoom::STATUS_NOSHOW, ['room_number' => '1105', 'total_rate' => 540000]);
        NoshowLog::create([
            'booking_room_id' => $charged->id, 'noshow_date' => '2026-09-02 10:00:00', 'noshow_time' => '10:26',
            'reason' => 'NightAudit No Show Charge Room - Khách không đến', 'status' => 4, 'username' => 'shift_user', 'shift' => '1',
        ]);

        $late = $this->makeRoom($booking, BookingRoom::STATUS_BOOKED, ['room_number' => '705', 'arrival_date' => '2026-09-03', 'departure_date' => '2026-09-04']);
        LateCheckin::create([
            'booking_room_id' => $late->id, 'late_checkin_date' => '2026-09-02 11:22:00', 'actual_arrival_date' => '2026-09-03 00:00:00',
            'late_checkin_time' => '11:22', 'reason' => 'NightAudit No Show One Day Charge Room', 'status' => 1, 'username' => 'shift_user', 'shift' => '1',
        ]);

        $rows = collect($this->getJson('/api/shift-work/noshow?from_date=2026-09-02&to_date=2026-09-02')
            ->assertOk()->json('data'))->keyBy('roomNumber');

        $this->assertSame('noshow', $rows['606']['type']);
        $this->assertFalse($rows['606']['isCharged']);
        $this->assertEquals(0, $rows['606']['totalAmount']);

        $this->assertTrue($rows['1105']['isCharged']);
        $this->assertEquals(540000, $rows['1105']['totalAmount']);

        $this->assertSame('late_checkin', $rows['705']['type']);
        $this->assertTrue($rows['705']['isCharged']);
        $this->assertEquals(540000, $rows['705']['totalAmount']);
        $this->assertSame(1, $rows['705']['nights']);
    }

    // Dòng 390
    public function test_departures_totals_follow_invoice_folios_and_blank_placeholder_rate_code(): void
    {
        $booking = $this->makeBooking('Walkin Guest');
        $room = $this->makeRoom($booking, BookingRoom::STATUS_CHECKED_IN, ['rate_code' => 'Vui lòng chọn giá phòng']);

        // Bill tiền phòng đã post cho phòng + bill thuộc Phiếu Tổng
        $this->bill(['RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'Amount' => 540000]);
        $this->bill(['RegisterId1' => $booking->id, 'Amount' => 3730000, 'ServiceId' => 'MB', 'DescriptionServive' => 'Minibar']);
        // Bill đã sửa không được cộng
        $this->bill(['RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'Amount' => 999999, 'Edit' => 1]);

        Payment::create([
            'booking_id' => $booking->id, 'booking_room_id' => $room->id, 'amount' => 100000, 'edit_flag' => 0, 'date' => '2026-09-02',
            'pack2' => 'PAY', 'status' => Payment::STATUS_PENDING,
        ]);

        $data = $this->getJson('/api/shift-work/departures?date=2026-09-03&status=all')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals(3730000, $data[0]['totalServices']);
        $this->assertEquals(0, $data[0]['totalPayment']);

        $roomRow = $data[0]['rooms'][0];
        $this->assertEquals(540000, $roomRow['totalServices']);
        $this->assertEquals(100000, $roomRow['totalPayment']);
        $this->assertSame('', $roomRow['rateCode']);
    }

    public function test_export_returns_xlsx_for_departures(): void
    {
        $booking = $this->makeBooking('Walkin Guest');
        $this->makeRoom($booking, BookingRoom::STATUS_CHECKED_IN);

        $response = $this->get('/api/shift-work/export?type=departures&date=2026-09-03&status=all');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringContainsString('phong_di_20260903.xlsx', $response->headers->get('content-disposition'));
    }
}
