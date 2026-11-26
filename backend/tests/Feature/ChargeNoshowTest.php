<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\Room;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\RoomNightBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChargeNoshowTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private BookingRoom $room;
    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Giả lập user đăng nhập
        $user = User::factory()->create(['username' => 'test_user']);
        $role = \App\Models\Role::create(['code' => 'noshow_test', 'name' => 'Noshow test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        $permission = \App\Models\Permission::firstOrCreate(['code' => 'fo.booking.noshow'], ['name' => 'Charge noshow', 'module' => 'FO']);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user);
        
        // Chèn các dữ liệu danh mục cần thiết
        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 4, 'name' => 'No Show'],
            ['id' => 25, 'name' => 'Noshow Status Value'],
        ]);

        DB::table('registration_statuses')->insert([
            ['id' => 1, 'booking_status_id' => 1, 'name' => 'Confirm', 'color' => '#ffffff'],
            ['id' => 4, 'booking_status_id' => 25, 'name' => 'Noshow', 'color' => '#ffffff'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-08-04',
            'actual_date' => '2026-08-04',
            'shift' => '1',
            'username' => 'test_user'
        ]);

        $form = RoomForm::create(['name' => 'Standard']);
        $class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        
        Room::create([
            'room_number' => '102',
            'room_form_id' => $form->id,
            'room_class_id' => $class->id,
            'floor' => 1,
            'status' => 'vacant_ready'
        ]);

        // Tạo booking ở trạng thái Noshow (status = 4)
        $this->booking = Booking::create([
            'booking_name' => 'Booking Noshow Test',
            'booking_date' => '2026-08-01',
            'arrival_date' => '2026-08-04',
            'departure_date' => '2026-08-06',
            'status' => Booking::STATUS_NO_SHOW, // 4
            'registration_status_id' => 25,
            'created_by' => 'test_user'
        ]);

        // Tạo phòng thuê noshow (status = 4)
        $this->room = BookingRoom::create([
            'booking_id' => $this->booking->id,
            'room_number' => '102',
            'room_class_id' => $class->id,
            'arrival_date' => '2026-08-04',
            'departure_date' => '2026-08-06',
            'rate' => 500000,
            'status' => BookingRoom::STATUS_NOSHOW // 4
        ]);

        $this->guest = Guest::create(['full_name' => 'Guest Noshow']);
        
        BookingRoomGuest::create([
            'booking_room_id' => $this->room->id,
            'guest_id' => $this->guest->id,
            'status' => 4, // Noshow
            'is_primary' => true
        ]);
    }

    private function charge(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/charge-noshow", $overrides + [
            'date_from' => '2026-08-04',
            'date_to' => '2026-08-05',
            'rate' => 1000000,
            'auto_rate' => false,
            'is_room_night' => 0,
            'description' => 'Dịch vụ phòng nghỉ noshow phòng 102',
            'reason' => 'Khách không đến nhưng charge 2 đêm',
        ]);
    }

    /**
     * Dòng 508: charge nhiều đêm phải post mỗi đêm một bill tiền phòng (đúng ngày đêm đó), không gộp thành 1 bill tổng.
     * Note 22/11: chọn từ ngày 04 đến ngày 05 là charge cả 2 ngày đã chọn.
     * Tự nhập tổng tiền 1.000.000 cho 2 đêm -> chia 2 bill 500.000 ngày 04 và 05.
     */
    public function test_charge_noshow_success_and_verifies_db_fields(): void
    {
        $this->charge()->assertStatus(200)->assertJson([
            'success' => true,
            'message' => 'Charge tiền phòng noshow thành công!',
        ]);

        // 1. Kiểm tra trạng thái booking đã tự động chuyển về 0 (Reservation)
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => Booking::STATUS_RESERVATION // 0
        ]);

        // 2. Mỗi đêm một hóa đơn service_bills (SP3000)
        $bills = ServiceBill::where('RentalRoomId1', $this->room->id)->orderBy('Date')->get();
        $this->assertCount(2, $bills);
        $this->assertSame(['2026-08-04', '2026-08-05'], $bills->map(fn ($b) => substr((string) $b->Date, 0, 10))->all());
        $this->assertSame([500000.0, 500000.0], $bills->map(fn ($b) => (float) $b->Amount)->all());
        foreach ($bills as $bill) {
            $this->assertSame('RM', $bill->ServiceId);
            $this->assertSame('Dịch vụ phòng nghỉ noshow phòng 102', $bill->DescriptionServive);
            // sp3000.RegisterId1=NULL , RentalRoomId1, CustomerId1= mã phòng, khách của phòng thao tác
            $this->assertNull($bill->RegisterId1);
            $this->assertSame($this->guest->id, $bill->CustomerId1);
            // RegisterID2= mã bk , RentalRoomId2, CustomerId2=NULL
            $this->assertSame($this->booking->id, (int) $bill->RegisterID2);
            $this->assertNull($bill->RentalRoomId2);
            $this->assertNull($bill->CustomerId2);
            $this->assertSame(1, (int) $bill->Status);

            // 3. Chi tiết hóa đơn service_bill_details (SP3001)
            $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $bill->Ma, 'Ma' => 1, 'ServiceId' => 'RM', 'Amount' => 500000]);
            // 4. Thống kê đêm phòng room_night_bills (SP3004), đúng ngày của đêm
            $this->assertDatabaseHas('room_night_bills', ['bill_id' => $bill->Ma, 'is_room_night' => 0, 'rate' => 500000, 'room' => '102']);
        }
        $this->assertSame(['2026-08-04', '2026-08-05'], RoomNightBill::orderBy('date')->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all());
    }

    public function test_auto_rate_posts_each_night_with_its_own_setup_price(): void
    {
        \App\Models\BookingRoomService::create([
            'booking_room_id' => $this->room->id, 'service_code' => 'RM', 'service_name' => 'RM',
            'service_date' => '2026-08-05', 'quantity' => 1, 'rate' => 300000, 'is_posted' => 0,
        ]);

        $this->charge(['auto_rate' => true, 'rate' => 800000])->assertStatus(200);

        $bills = ServiceBill::where('RentalRoomId1', $this->room->id)->orderBy('Date')->get();
        $this->assertSame([500000.0, 300000.0], $bills->map(fn ($b) => (float) $b->Amount)->all());
        $this->assertSame(['2026-08-04', '2026-08-05'], $bills->map(fn ($b) => substr((string) $b->Date, 0, 10))->all());
    }

    public function test_manual_total_is_split_per_night_without_losing_a_dong(): void
    {
        $this->charge(['date_to' => '2026-08-06', 'rate' => 1000000])->assertStatus(200);

        $amounts = ServiceBill::where('RentalRoomId1', $this->room->id)->orderBy('Date')->pluck('Amount')->map(fn ($a) => (float) $a)->all();
        $this->assertCount(3, $amounts);
        $this->assertEqualsWithDelta(1000000.0, array_sum($amounts), 0.01);
    }

    public function test_same_day_range_still_posts_a_single_bill(): void
    {
        $this->charge(['date_to' => '2026-08-04', 'auto_rate' => true])->assertStatus(200);

        $bills = ServiceBill::where('RentalRoomId1', $this->room->id)->get();
        $this->assertCount(1, $bills);
        $this->assertSame(500000.0, (float) $bills->first()->Amount);
    }

    /**
     * Test case: Không cho phép charge noshow nếu phòng không ở trạng thái noshow.
     */
    public function test_cannot_charge_noshow_if_room_status_is_not_noshow(): void
    {
        // Chuyển trạng thái phòng sang Đã checkin
        $this->room->update(['status' => BookingRoom::STATUS_CHECKED_IN]);

        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/charge-noshow", [
            'date_from' => '2026-08-04',
            'date_to' => '2026-08-06',
            'rate' => 540000,
            'auto_rate' => true,
            'is_room_night' => 0,
            'description' => 'Dịch vụ phòng nghỉ noshow phòng 102',
            'reason' => 'Test'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Phòng thuê không ở trạng thái Noshow, không thể thực hiện charge tiền.'
        ]);
    }

    public function test_no_post_booking_blocks_noshow_charge_with_standard_warning(): void
    {
        $this->booking->update(['no_post' => true]);

        $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/charge-noshow", [
            'date_from' => '2026-08-04', 'date_to' => '2026-08-06',
            'rate' => 1000000, 'auto_rate' => false,
            'description' => 'No Post regression test',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Phòng đang ở trạng thái No Post. Vui lòng kiểm tra lại thông tin.');

        $this->assertDatabaseCount('service_bills', 0);
    }

    /** Phòng late check-in (noshow one day): ngày đến đã dời sang hôm sau, vẫn trạng thái Đặt trước. */
    private function makeLateCheckinRoom(string $lateDate = '2026-08-04'): void
    {
        $this->booking->update(['status' => Booking::STATUS_RESERVATION]);
        $this->room->update(['status' => BookingRoom::STATUS_BOOKED, 'arrival_date' => '2026-08-05', 'no_show_day' => 1]);
        \App\Models\LateCheckin::create([
            'booking_room_id' => $this->room->id,
            'late_checkin_date' => $lateDate . ' 00:00:00',
            'actual_arrival_date' => '2026-08-05 00:00:00',
            'late_checkin_time' => '18:00',
            'reason' => 'NightAudit No Show One Day No Charge',
            'status' => 1,
            'username' => 'test_user',
            'shift' => '1',
        ]);
    }

    /**
     * Dòng 532: phòng late check-in (noshow one day) chưa tới cũng charge bù được đúng ngày vắng.
     */
    public function test_row_532_can_charge_noshow_one_day_room_for_its_absent_date(): void
    {
        $this->makeLateCheckinRoom();

        $this->charge(['date_from' => '2026-08-04', 'date_to' => '2026-08-04', 'rate' => 500000])->assertOk();

        $bills = ServiceBill::where('RentalRoomId1', $this->room->id)->where('ServiceId', 'RM')->get();
        $this->assertCount(1, $bills);
        $this->assertSame('2026-08-04', substr((string) $bills[0]->Date, 0, 10));
        $this->assertEquals(500000, $bills[0]->Amount);
        $this->assertSame('Dịch vụ phòng nghỉ noshow phòng 102', $bills[0]->DescriptionServive);
        // Phòng vẫn là đặt trước, ngày đến không bị đổi
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $this->room->fresh()->status);
        $this->assertSame('2026-08-05', $this->room->fresh()->arrival_date->toDateString());
    }

    public function test_row_532_one_day_room_cannot_charge_a_date_that_was_not_absent(): void
    {
        $this->makeLateCheckinRoom();

        $this->charge(['date_from' => '2026-08-05', 'date_to' => '2026-08-05', 'rate' => 500000])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertDatabaseCount('service_bills', 0);
    }

    public function test_row_532_one_day_room_cannot_be_charged_twice_for_the_same_night(): void
    {
        $this->makeLateCheckinRoom();
        $this->charge(['date_from' => '2026-08-04', 'date_to' => '2026-08-04', 'rate' => 500000])->assertOk();

        $this->charge(['date_from' => '2026-08-04', 'date_to' => '2026-08-04', 'rate' => 500000])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertSame(1, ServiceBill::where('RentalRoomId1', $this->room->id)->where('ServiceId', 'RM')->count());
    }

    public function test_row_532_noshow_list_exposes_ids_dates_and_charged_flag_for_both_types(): void
    {
        $this->makeLateCheckinRoom();
        $path = '/api/shift-work/noshow?from_date=2026-08-04&to_date=2026-08-04';

        $row = collect($this->getJson($path)->assertOk()->json('data'))->firstWhere('type', 'late_checkin');
        $this->assertSame($this->booking->id, $row['bookingId']);
        $this->assertSame($this->room->id, $row['roomId']);
        $this->assertSame('2026-08-04', $row['noshowDateIso']);
        $this->assertSame('2026-08-05', $row['arrivalDateIso']);
        $this->assertFalse($row['charged']);

        $this->charge(['date_from' => '2026-08-04', 'date_to' => '2026-08-04', 'rate' => 500000])->assertOk();

        $row = collect($this->getJson($path)->assertOk()->json('data'))->firstWhere('type', 'late_checkin');
        $this->assertTrue($row['charged']);
    }

    public function test_row_532_booked_room_without_late_checkin_still_cannot_be_charged(): void
    {
        $this->booking->update(['status' => Booking::STATUS_RESERVATION]);
        $this->room->update(['status' => BookingRoom::STATUS_BOOKED]);

        $this->charge()->assertStatus(422)
            ->assertJsonPath('message', 'Phòng thuê không ở trạng thái Noshow, không thể thực hiện charge tiền.');
    }
}
