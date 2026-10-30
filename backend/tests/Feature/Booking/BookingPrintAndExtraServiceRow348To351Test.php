<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\Department;
use App\Models\HotelService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 348, 349, 351 (note 29/10) - sheet "THEO DÕI VẤN ĐỀ CẦN ĐIỀU CHỈNH".
 * Ngày hệ thống 07/08/2026, phòng 101 ở 07/08 -> 09/08 (2 đêm), giá phòng 540,000.
 */
class BookingPrintAndExtraServiceRow348To351Test extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create(['code' => 'row348_test', 'name' => 'Row 348 test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['fo.booking.view', 'fo.booking.edit', 'fo.service.view', 'fo.service.add', 'fo.service.edit', 'fo.service.delete'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'], ['id' => 4, 'name' => 'No Show'], ['id' => 100, 'name' => 'Chuyển phòng'],
        ]);
        SystemDateRoll::create([
            'system_date' => '2026-08-07 00:00:00', 'actual_date' => '2026-08-07 00:00:00', 'shift' => '1', 'username' => 'test_user',
        ]);

        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard Class', 'is_active' => true]);
        $roomForm = RoomForm::create(['name' => 'Standard Form']);
        foreach (['101', '102'] as $number) {
            Room::create([
                'room_number' => $number, 'room_class_id' => $this->roomClass->id, 'room_form_id' => $roomForm->id,
                'floor' => 1, 'status' => 'available',
            ]);
        }
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Front Office']);
        HotelService::updateOrCreate(['code' => 'RM'], ['name' => 'Dịch vụ phòng nghỉ', 'price' => 0, 'is_active' => true, 'department_id' => $department->id]);
        HotelService::updateOrCreate(['code' => 'TOUR'], ['name' => 'Tour', 'price' => 200000, 'is_active' => true, 'department_id' => $department->id]);
        HotelService::updateOrCreate(['code' => 'DINNER'], ['name' => 'Ăn tối', 'price' => 500000, 'is_active' => true, 'department_id' => $department->id]);
        HotelService::updateOrCreate(['code' => 'EB'], ['name' => 'Giường phụ', 'price' => 300000, 'is_active' => true, 'department_id' => $department->id]);
    }

    // ------------------------------------------------------------------
    // Dòng 348: In phiếu đăng ký khách - giá phòng hiển thị 0đ
    // ------------------------------------------------------------------

    public function test_row348_room_rate_comes_from_booking_room_rate_when_no_nightly_rows(): void
    {
        [$booking, $room] = $this->inhouseRoom();

        $data = $this->printData($booking);

        $this->assertEquals(540000, $data['rooms'][0]['rate']);
        $this->assertEquals(1080000, $data['rooms'][0]['total_amount']);
        $this->assertEquals(1080000, $data['summary']['total_room']);
        $this->assertCount(2, $this->roomLines($data));
    }

    public function test_row348_room_rate_uses_nightly_rm_rows_and_averages_them(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'RM', '2026-08-07', 600000);
        $this->addService($room, 'RM', '2026-08-08', 500000);

        $data = $this->printData($booking);

        $this->assertEquals(550000, $data['rooms'][0]['rate']);
        $this->assertEquals([600000, 500000], array_column($this->roomLines($data), 'amount'));
    }

    public function test_row348_extra_bed_charged_to_room_folio_is_not_added_to_room_rate(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'EB', '2026-08-07', 300000, isRoom: 1);

        $data = $this->printData($booking);

        $this->assertEquals(540000, $data['rooms'][0]['rate']);
        $eb = collect($data['services'])->firstWhere('code', 'EB');
        $this->assertFalse($eb['is_room']);
        $this->assertSame('service', $eb['type']);
        $this->assertEquals(300000, $data['summary']['total_sale_service']);
    }

    public function test_row348_past_night_without_bill_is_not_charged(): void
    {
        [$booking, $room] = $this->inhouseRoom(arrival: '2026-08-06');

        $data = $this->printData($booking);

        // 06/08 là ngày quá khứ chưa có bill -> không tính; 07/08 và 08/08 theo giá phòng.
        $this->assertEquals(['07/08/2026', '08/08/2026'], array_column($this->roomLines($data), 'date'));
    }

    // ------------------------------------------------------------------
    // Dòng 349: In hóa đơn tạm tính - không có tiền phòng, chỉ in dịch vụ
    // ------------------------------------------------------------------

    public function test_row349_posted_room_charge_is_a_room_line_and_not_duplicated(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->postRoomCharge($room, '2026-08-07');

        $data = $this->printData($booking);
        $roomLines = $this->roomLines($data);

        $this->assertCount(2, $roomLines, 'đêm 07/08 theo bill, đêm 08/08 theo giá phòng');
        $this->assertSame('fo', $roomLines[0]['source']);
        $this->assertSame('sale', $roomLines[1]['source']);
        $this->assertSame([], array_values(array_filter($data['services'], fn ($s) => !$s['is_room'])),
            '"In dịch vụ" không được chứa bill tiền phòng');
        $this->assertEquals(1080000, $data['summary']['total_room']);
        $this->assertEquals(1080000, $data['summary']['total_all']);
    }

    public function test_row349_posted_extra_service_is_printed_once_as_service(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07'); // Tour được post cùng tiền phòng

        $data = $this->printData($booking);
        $tours = array_values(array_filter($data['services'], fn ($s) => $s['code'] === 'TOUR'));

        $this->assertCount(1, $tours);
        $this->assertFalse($tours[0]['is_room']);
        $this->assertSame('fo', $tours[0]['source']);
        $this->assertEquals(1080000 + 200000, $data['summary']['total_all']);
    }

    public function test_row349_selected_room_only_prints_that_room(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $other = $this->makeRoom($booking, 'G-349-B', '102', 700000);

        $data = $this->printData($booking, [$other->id]);

        $this->assertCount(1, $data['rooms']);
        $this->assertEquals(700000, $data['rooms'][0]['rate']);
        $this->assertEquals([$other->id, $other->id], array_column($this->roomLines($data), 'room_id'));
    }

    // ------------------------------------------------------------------
    // Dòng 351: Dịch vụ bổ sung - sửa giá sau khi bill đã chạy
    // ------------------------------------------------------------------

    public function test_row351_editing_posted_extra_service_keeps_bill_and_posted_row(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');
        $posted = $this->tourRows($room)->firstWhere('is_posted', 1);
        $this->assertNotNull($posted?->service_bill_id);

        $this->addService($room, 'TOUR', '2026-08-07', 300000)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(200000, (float) ServiceBill::find($posted->service_bill_id)->Amount, 'bill ở màn hình hóa đơn giữ 200k');
        $this->assertEquals(200000, (float) $posted->fresh()->rate, 'dòng đã post giữ 200k');
        $this->assertSame(1, (int) $posted->fresh()->is_posted);

        $plan = $this->tourRows($room)->where('is_posted', 0)->whereNull('service_bill_id')->values();
        $this->assertCount(1, $plan);
        $this->assertEquals(300000, (float) $plan[0]->rate);
    }

    public function test_row351_editing_again_updates_the_same_plan_row(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');

        $this->addService($room, 'TOUR', '2026-08-07', 300000)->assertOk();
        $this->addService($room, 'TOUR', '2026-08-07', 350000)->assertOk();

        $plan = $this->tourRows($room)->where('is_posted', 0)->values();
        $this->assertCount(1, $plan);
        $this->assertEquals(350000, (float) $plan[0]->rate);
    }

    public function test_row351_paid_extra_service_bill_cannot_be_repriced(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');
        $posted = $this->tourRows($room)->firstWhere('is_posted', 1);
        ServiceBill::whereKey($posted->service_bill_id)->update(['PaymentId' => 999]);

        $this->addService($room, 'TOUR', '2026-08-07', 300000)->assertStatus(422);

        $this->assertCount(1, $this->tourRows($room));
        $this->assertEquals(200000, (float) $posted->fresh()->rate);
    }

    public function test_row351_unposted_night_is_updated_directly(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-08', 200000);

        $this->addService($room, 'TOUR', '2026-08-08', 300000)->assertSuccessful();

        $rows = $this->tourRows($room);
        $this->assertCount(1, $rows);
        $this->assertEquals(300000, (float) $rows[0]->rate);
    }

    public function test_row351_plan_row_is_not_posted_twice_while_old_bill_is_active(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');
        $this->addService($room, 'TOUR', '2026-08-07', 300000)->assertOk();

        // Xóa bill tiền phòng rồi chạy lại tiền phòng, bill Tour cũ vẫn còn.
        $rmBill = ServiceBill::where('RentalRoomId1', $room->id)->where('ServiceId', 'RM')->where('Status', '!=', 3)->firstOrFail();
        $this->cancelBills($room, [$rmBill->Ma]);
        $this->postRoomCharge($room, '2026-08-07');

        $activeTourBills = $this->activeBills($room, 'TOUR');
        $this->assertCount(1, $activeTourBills);
        $this->assertEquals(200000, (float) $activeTourBills[0]->Amount);
    }

    public function test_row351_new_price_applies_after_bills_are_deleted_and_reposted(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');
        $this->addService($room, 'TOUR', '2026-08-07', 300000)->assertOk();

        $this->cancelBills($room, $this->activeBills($room)->pluck('Ma')->all());
        $this->postRoomCharge($room, '2026-08-07');

        $activeTourBills = $this->activeBills($room, 'TOUR');
        $this->assertCount(1, $activeTourBills);
        $this->assertEquals(300000, (float) $activeTourBills[0]->Amount);
    }

    public function test_row351_print_shows_the_bill_that_was_run_not_the_pending_price(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->postRoomCharge($room, '2026-08-07');
        $this->addService($room, 'TOUR', '2026-08-07', 300000)->assertOk();

        $tours = array_values(array_filter($this->printData($booking)['services'], fn ($s) => $s['code'] === 'TOUR'));

        $this->assertCount(1, $tours);
        $this->assertEquals(200000, $tours[0]['amount']);
    }

    public function test_row351_adding_dinner_does_not_touch_existing_tour(): void
    {
        [$booking, $room] = $this->inhouseRoom();
        $this->addService($room, 'TOUR', '2026-08-07', 200000);
        $this->addService($room, 'TOUR', '2026-08-08', 200000);

        // Modal chỉ gửi dịch vụ mới (Ăn tối đêm cuối).
        $this->addService($room, 'DINNER', '2026-08-08', 500000)->assertSuccessful();

        $this->assertEquals([200000, 200000], $this->tourRows($room)->pluck('rate')->map(fn ($r) => (float) $r)->all());
        $this->assertCount(1, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'DINNER')->get());
    }

    // ------------------------------------------------------------------

    /** @return array{0: Booking, 1: BookingRoom} */
    private function inhouseRoom(string $arrival = '2026-08-07'): array
    {
        $booking = Booking::create([
            'booking_name' => 'Test Booking', 'arrival_date' => $arrival, 'departure_date' => '2026-08-09',
            'booking_date' => $arrival, 'created_by' => 'test_user', 'status' => Booking::STATUS_CHECKIN,
            'is_master_room_rate' => false, // bill tiền phòng nằm ở folio phòng như ảnh của khách
        ]);

        return [$booking, $this->makeRoom($booking, 'G-348-A', '101', 540000, $arrival)];
    }

    private function makeRoom(Booking $booking, string $id, string $roomNumber, float $rate, string $arrival = '2026-08-07'): BookingRoom
    {
        return BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id, 'room_number' => $roomNumber,
            'arrival_date' => $arrival, 'departure_date' => '2026-08-09', 'rate' => $rate, 'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
    }

    private function addService(BookingRoom $room, string $code, string $date, float $rate, int $isRoom = 1)
    {
        return $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => $code, 'service_date' => $date, 'quantity' => 1, 'rate' => $rate, 'is_room' => $isRoom,
        ]);
    }

    private function postRoomCharge(BookingRoom $room, string $date): void
    {
        $this->postJson('/api/booking-room-services/post-room-charge', [
            'booking_room_id' => $room->id, 'date_from' => $date, 'date_to' => $date, 'mode' => 'auto',
        ])->assertSuccessful();
    }

    /** Xóa bill ở màn hình hóa đơn: gửi bill và các dòng dịch vụ gắn với bill đó. */
    private function cancelBills(BookingRoom $room, array $billIds): void
    {
        $this->postJson("/api/booking-rooms/{$room->id}/services/cancel", [
            'service_bill_ids' => $billIds,
            'service_ids' => BookingRoomService::where('booking_room_id', $room->id)->whereIn('service_bill_id', $billIds)->pluck('id')->all(),
            'reason' => 'Chạy lại giá',
        ])->assertSuccessful();
    }

    private function printData(Booking $booking, array $roomIds = []): array
    {
        return $this->getJson("/api/bookings/{$booking->id}/print-data" . ($roomIds ? '?room_ids=' . implode(',', $roomIds) : ''))
            ->assertOk()
            ->json('data');
    }

    private function roomLines(array $data): array
    {
        return array_values(array_filter($data['services'], fn ($s) => $s['is_room']));
    }

    private function tourRows(BookingRoom $room)
    {
        return BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'TOUR')->orderBy('id')->get();
    }

    private function activeBills(BookingRoom $room, ?string $code = null)
    {
        return ServiceBill::where('RentalRoomId1', $room->id)
            ->when($code, fn ($q) => $q->where('ServiceId', $code))
            ->whereNotIn('Status', [3, 4])
            ->where(fn ($q) => $q->whereNull('Edit')->orWhere('Edit', '!=', 1))
            ->where('Amount', '>', 0)
            ->get();
    }
}
