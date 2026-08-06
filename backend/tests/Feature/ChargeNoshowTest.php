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
        $this->actingAs(User::factory()->create(['username' => 'test_user']));
        
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
            'registration_status_id' => 4,
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

    /**
     * Test case: Thực hiện charge noshow thành công và xác thực dữ liệu hóa đơn chèn vào DB.
     */
    public function test_charge_noshow_success_and_verifies_db_fields(): void
    {
        $response = $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$this->room->id}/charge-noshow", [
            'date_from' => '2026-08-04',
            'date_to' => '2026-08-06',
            'rate' => 1000000, // Tự nhập tổng tiền
            'auto_rate' => false,
            'is_room_night' => 0, // Bổ sung không tính công suất
            'description' => 'Dịch vụ phòng nghỉ noshow phòng 102',
            'reason' => 'Khách không đến nhưng charge 2 đêm'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Charge tiền phòng noshow thành công!'
        ]);

        // 1. Kiểm tra trạng thái booking đã tự động chuyển về 0 (Reservation)
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => Booking::STATUS_RESERVATION // 0
        ]);

        // 2. Kiểm tra hóa đơn service_bills (SP3000)
        $this->assertDatabaseHas('service_bills', [
            'ServiceId' => 'RM',
            'Amount' => 1000000,
            'DescriptionServive' => 'Dịch vụ phòng nghỉ noshow phòng 102',
            // sp3000.RegisterId1=NULL , RentalRoomId1, CustomerId1= mã phòng, khách của phòng thao tác
            'RegisterId1' => null,
            'RentalRoomId1' => $this->room->id,
            'CustomerId1' => $this->guest->id,
            // RegisterID2= mã bk , RentalRoomId2, CustomerId2=NULL
            'RegisterID2' => $this->booking->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
            'Status' => 1
        ]);

        $bill = ServiceBill::where('RentalRoomId1', $this->room->id)->first();
        $this->assertNotNull($bill);

        // 3. Kiểm tra chi tiết hóa đơn service_bill_details (SP3001)
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $bill->Ma,
            'Ma' => 1,
            'ServiceId' => 'RM',
            'Amount' => 1000000
        ]);

        // 4. Kiểm tra thống kê đêm phòng room_night_bills (SP3004)
        $this->assertDatabaseHas('room_night_bills', [
            'bill_id' => $bill->Ma,
            'is_room_night' => 0,
            'rate' => 1000000,
            'room' => '102'
        ]);
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
}
