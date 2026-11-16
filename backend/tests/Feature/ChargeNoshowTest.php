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
            'date_to' => '2026-08-06',
            'rate' => 1000000,
            'auto_rate' => false,
            'is_room_night' => 0,
            'description' => 'Dịch vụ phòng nghỉ noshow phòng 102',
            'reason' => 'Khách không đến nhưng charge 2 đêm',
        ]);
    }

    /**
     * Dòng 508: charge nhiều đêm phải post mỗi đêm một bill tiền phòng (đúng ngày đêm đó), không gộp thành 1 bill tổng.
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
        $this->charge(['date_to' => '2026-08-07', 'rate' => 1000000])->assertStatus(200);

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
}
