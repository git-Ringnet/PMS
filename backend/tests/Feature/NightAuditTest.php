<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\User;
use App\Models\RegistrationStatus;
use App\Models\SystemDateRoll;
use App\Models\HotelSetting;
use App\Models\HotelConfig;
use App\Models\HotelService;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomLock;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\RoomNightBill;
use App\Models\BookingRoomService;
use App\Models\LateCheckin;
use App\Models\NoshowLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class NightAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'SystemConfigurationSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentSeeder']);
        $this->artisan('db:seed', ['--class' => 'HotelDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SpecialRequestSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDateRollSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);

        $this->user = User::factory()->create(['username' => 'admin']);
    }

    /**
     * Test quét phòng check status
     */
    public function test_check_status_returns_pending_checkins_and_checkouts()
    {
        // Lấy ngày hệ thống hiện tại
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        // Tạo phòng pending checkin (arrival <= system_date, status = 0)
        $booking = Booking::create([
            'booking_name' => 'NGUYEN VAN A',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        $bookingRoom = BookingRoom::create([
            'id' => 'G1000001',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_BOOKED, // Booked
            'rate' => 500000,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/night-audit/check-status');

        $response->assertSuccessful()
            ->assertJsonPath('data.pending_checkins_count', 1)
            ->assertJsonPath('data.pending_checkouts_count', 0);
    }

    /**
     * Test Late Check-in (Noshow One Day)
     */
    public function test_late_check_in_moves_date_and_creates_log()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        $booking = Booking::create([
            'booking_name' => 'NGUYEN VAN A',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        $bookingRoom = BookingRoom::create([
            'id' => 'G1000002',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_BOOKED,
            'rate' => 500000,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/late-check-in', [
            'booking_room_id' => $bookingRoom->id,
            'charge_option' => 'room_only',
            'reason' => 'Late checkin test'
        ]);

        $response->assertSuccessful();

        // Kiểm tra booking_room đã dời ngày đến sang hôm sau
        $updatedRoom = BookingRoom::find($bookingRoom->id);
        $nextDateStr = Carbon::parse($sysDateStr)->addDay()->toDateString();
        $this->assertEquals($nextDateStr, $updatedRoom->arrival_date->toDateString());
        $this->assertEquals(1, $updatedRoom->no_show_day);

        // Kiểm tra log trong late_checkins
        $this->assertDatabaseHas('late_checkins', [
            'booking_room_id' => $bookingRoom->id,
            'username' => 'admin',
        ]);

        // Kiểm tra đã post bill
        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $bookingRoom->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId2' => null,
            'ServiceId' => 'RM',
            'Amount' => 500000
        ]);
    }

    /**
     * Test Noshow hoàn toàn giải phóng phòng
     */
    public function test_no_show_cancels_room_and_releases_physical_room()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        // Tạo phòng vật lý
        $room = Room::updateOrCreate(
            ['room_number' => '102'],
            [
                'room_class_id' => 1,
                'room_form_id' => 1,
                'floor' => '1',
                'room_status_code' => 'occupied_dirty',
                'status' => 'dirty'
            ]
        );

        $booking = Booking::create([
            'booking_name' => 'NGUYEN VAN A',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        $bookingRoom = BookingRoom::create([
            'id' => 'G1000003',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'room_number' => '102',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_BOOKED,
            'rate' => 500000,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/no-show', [
            'booking_room_id' => $bookingRoom->id,
            'charge_option' => 'no_charge',
            'reason' => 'Noshow test'
        ]);

        $response->assertSuccessful();

        // BookingRoom status = 4 (No show)
        $this->assertEquals(4, BookingRoom::find($bookingRoom->id)->status);

        // Room status = vacant_ready
        $this->assertEquals('vacant_ready', Room::where('room_number', '102')->first()->room_status_code);

        // Log noshow
        $this->assertDatabaseHas('noshow_logs', [
            'booking_room_id' => $bookingRoom->id,
        ]);
    }

    /**
     * Test Sang ngày chính thức
     */
    public function test_run_night_audit_rolls_date_and_posts_charge()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        // Tạo phòng vật lý
        $room = Room::updateOrCreate(
            ['room_number' => '103'],
            [
                'room_class_id' => 1,
                'room_form_id' => 1,
                'floor' => '1',
                'room_status_code' => 'occupied_ready',
            ]
        );

        // Tạo booking in-house
        $booking = Booking::create([
            'booking_name' => 'NGUYEN VAN A',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        $bookingRoom = BookingRoom::create([
            'id' => 'G1000004',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'room_number' => '103',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_CHECKED_IN, // In house
            'rate' => 600000,
        ]);
        $booking->update(['is_master_room_rate' => true]);

        $department = Department::where('code', 'FO')->firstOrFail();
        HotelService::where('code', 'RM')->update([
            'service_charge' => 5, 'special_tax' => 1, 'tax' => 8,
        ]);
        HotelService::where('code', 'BF')->update([
            'service_charge' => 3, 'special_tax' => 2, 'tax' => 10,
        ]);
        $childBreakfast = HotelService::updateOrCreate(
            ['code' => 'BD'],
            [
                'name' => 'Phụ thu ăn sáng trẻ em', 'price' => 90000,
                'service_charge' => 4, 'special_tax' => 2, 'tax' => 9,
                'department_id' => $department->id,
            ]
        );
        $department->hotelServices()->syncWithoutDetaching([
            $childBreakfast->id => ['description' => 'Phụ thu ăn sáng trẻ em'],
        ]);
        HotelConfig::updateOrCreate(
            ['name' => 'Booking_BFChildSetServiceId'],
            ['value' => 'BD']
        );
        $setupService = BookingRoomService::create([
            'booking_room_id' => $bookingRoom->id,
            'service_code' => 'BD',
            'service_name' => 'Phụ thu ăn sáng trẻ em',
            'service_date' => $sysDateStr,
            'quantity' => 1,
            'rate' => 90000,
            'folio' => 1,
            'is_room' => 1,
            'is_posted' => 0,
            'note' => 'Phụ thu ăn sáng trẻ em: Child 1',
        ]);

        // Chạy sang ngày
        $response = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => true,
            'empty_to_inspect' => true
        ]);

        $response->assertSuccessful();

        // Verify ngày hệ thống mới
        $newRoll = SystemDateRoll::latest('id')->first();
        $nextDateStr = Carbon::parse($sysDateStr)->addDay()->toDateString();
        $this->assertEquals($nextDateStr, Carbon::parse($newRoll->system_date)->toDateString());

        // Verify tiền phòng đã post
        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $bookingRoom->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId2' => null,
            'ServiceId' => 'RM',
            'Amount' => 600000,
            'ServiceCharge' => 5,
            'SpecialTax' => 1,
            'Tax' => 8,
        ]);
        $roomBill = ServiceBill::where('RentalRoomId1', $bookingRoom->id)->where('ServiceId', 'RM')->firstOrFail();
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $roomBill->Ma, 'ServiceId' => 'RM',
            'ServiceCharge' => 5, 'SpecialTax' => 1, 'Tax' => 8,
        ]);

        $setupService->refresh();
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $setupService->service_bill_id,
            'ServiceId' => 'BD',
            'DescriptionServive' => 'Phụ thu ăn sáng trẻ em - Child 1',
            'ServiceCharge' => 4,
            'SpecialTax' => 2,
            'Tax' => 9,
        ]);
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $setupService->service_bill_id,
            'ServiceId' => 'BD',
            'DescriptionServive' => 'Phụ thu ăn sáng trẻ em - Child 1',
            'ServiceCharge' => 4,
            'SpecialTax' => 2,
            'Tax' => 9,
        ]);

        // Verify room status changed to occupied_dirty (12)
        $this->assertEquals('occupied_dirty', Room::where('room_number', '103')->first()->room_status_code);
    }

    public function test_split_old_services_recalculates_breakfast_details_and_skips_vat_bill()
    {
        $systemDate = Carbon::parse(SystemDateRoll::latest('id')->value('system_date'))->startOfDay();
        $serviceDate = $systemDate->copy()->subDay();
        HotelSetting::query()->firstOrFail()->update(['breakfast_adult_rate' => 100000]);

        $booking = Booking::create([
            'booking_name' => 'Khách test tách dịch vụ', 'arrival_date' => $serviceDate,
            'departure_date' => $systemDate->copy()->addDay(), 'num_of_days' => 2,
            'booking_date' => $serviceDate, 'created_by' => 'admin', 'registration_status_id' => 1,
        ]);
        $room = BookingRoom::create([
            'id' => 'G1999001', 'booking_id' => $booking->id, 'room_class_id' => 1,
            'room_number' => '901', 'arrival_date' => $serviceDate,
            'departure_date' => $systemDate->copy()->addDay(), 'status' => BookingRoom::STATUS_CHECKED_IN,
            'rate' => 600000, 'adults' => 3, 'breakfast' => true,
        ]);

        $bill = $this->makeRoomNightBill($booking, $room, $serviceDate, null);
        ServiceBillDetail::create(['BillServiceId' => $bill->Ma, 'Ma' => 1, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => 600000, 'Amount' => 600000]);
        ServiceBillDetail::create(['BillServiceId' => $bill->Ma, 'Ma' => 2, 'DepartmentId' => 'FO', 'ServiceId' => 'BF', 'OriginalRate' => 450000, 'Amount' => 450000]);
        ServiceBillDetail::create(['BillServiceId' => $bill->Ma, 'Ma' => 3, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => -450000, 'Amount' => -450000]);

        $vatBill = $this->makeRoomNightBill($booking, $room, $serviceDate, 99);
        ServiceBillDetail::create(['BillServiceId' => $vatBill->Ma, 'Ma' => 1, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => 600000, 'Amount' => 600000]);
        ServiceBillDetail::create(['BillServiceId' => $vatBill->Ma, 'Ma' => 2, 'DepartmentId' => 'FO', 'ServiceId' => 'BF', 'OriginalRate' => 450000, 'Amount' => 450000, 'VatNumber' => 'VAT-001']);
        ServiceBillDetail::create(['BillServiceId' => $vatBill->Ma, 'Ma' => 3, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => -450000, 'Amount' => -450000]);
        // Dữ liệu cũ không có SP3004 vẫn phải tách lại được từ SP3000/SP3001.
        $legacyBill = $this->makeRoomNightBill($booking, $room, $serviceDate, null, false);
        ServiceBillDetail::create(['BillServiceId' => $legacyBill->Ma, 'Ma' => 1, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => 600000, 'Amount' => 600000]);
        ServiceBillDetail::create(['BillServiceId' => $legacyBill->Ma, 'Ma' => 2, 'DepartmentId' => 'FO', 'ServiceId' => 'BF', 'OriginalRate' => 450000, 'Amount' => 450000]);
        ServiceBillDetail::create(['BillServiceId' => $legacyBill->Ma, 'Ma' => 3, 'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'OriginalRate' => -450000, 'Amount' => -450000]);
        // Giá BF cũ 150k x 3 = 450k; sau điều chỉnh còn 100k và 2 người lớn.
        $room->update(['adults' => 2]);
        $postedDate = RoomNightBill::findOrFail($bill->Ma)->date->toDateString();

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/split-old-services', [
            'from_date' => $postedDate, 'to_date' => $postedDate,
        ]);
        $response->assertSuccessful()->assertJsonPath('data.updated', 2)->assertJsonPath('data.skipped_vat', 1);

        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $bill->Ma, 'Ma' => 2, 'Amount' => 200000]);
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $bill->Ma, 'Ma' => 2, 'OriginalRate' => 100000, 'Quantity' => 2]);
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $bill->Ma, 'Ma' => 3, 'Amount' => -200000]);
        $this->assertDatabaseHas('room_night_bills', ['bill_id' => $bill->Ma, 'adult' => 2, 'breakfast_amount' => 200000]);
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $legacyBill->Ma, 'Ma' => 2, 'Amount' => 200000, 'OriginalRate' => 100000, 'Quantity' => 2]);
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $vatBill->Ma, 'Ma' => 2, 'Amount' => 450000, 'VatNumber' => 'VAT-001']);
    }

    private function makeRoomNightBill(Booking $booking, BookingRoom $room, Carbon $date, ?int $vatId, bool $createMetadata = true): ServiceBill
    {
        $bill = ServiceBill::create([
            'Date' => $date, 'OpenTime' => '12:00', 'Guest' => $booking->booking_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Quantity' => 1, 'Amount' => 600000,
            'Currency' => 'VND', 'Exchange' => 1, 'VatId' => $vatId, 'Folio' => '1',
            'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'RegisterID2' => $booking->id,
            'RentalRoomId2' => $room->id, 'Username' => 'admin', 'Status' => 1,
        ]);
        if ($createMetadata) RoomNightBill::create([
            'bill_id' => $bill->Ma, 'adult' => 3, 'is_room_night' => 1,
            'date' => $date->toDateString(), 'room' => $room->room_number, 'room_type_id' => $room->room_class_id,
            'breakfast' => 3, 'breakfast_amount' => 450000, 'rate' => 600000,
        ]);
        return $bill;
    }
}
