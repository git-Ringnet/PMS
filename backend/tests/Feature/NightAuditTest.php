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
use App\Models\NightAuditRun;
use App\Models\NightAuditRunStep;
use App\Models\NightAuditAgencyProductivitySnapshot;
use App\Models\NightAuditInhouseSnapshot;
use App\Models\NightAuditRoomSalesForecastSnapshot;
use App\Models\NightAuditRoomTypeSnapshot;
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
            'room_number' => '101',
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
     * Test phòng đã chuyển (status = 100 / STATUS_MOVED) không bị tính vào pending_checkins và không chặn sang ngày
     */
    public function test_moved_room_status_100_is_ignored_by_check_status_and_run_audit()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        $booking = Booking::create([
            'booking_name' => 'NGUYEN VAN B',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        // Phòng đã chuyển (status = 100), đến hôm nay
        BookingRoom::create([
            'id' => 'G1000099',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'room_number' => '101',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_MOVED, // 100
            'rate' => 500000,
        ]);

        // 1. check-status phải trả về 0 pending checkins
        $response = $this->actingAs($this->user)->getJson('/api/night-audit/check-status');
        $response->assertSuccessful()
            ->assertJsonPath('data.pending_checkins_count', 0)
            ->assertJsonPath('data.pending_checkouts_count', 0);

        // 2. run night audit phải thành công bình thường (không bị exception chặn)
        $runResponse = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => false,
            'empty_to_inspect' => false,
        ]);
        $runResponse->assertSuccessful();
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

    /**
     * Test Night Audit tạo đủ run record, 13 steps, và các snapshot SP7000, SP7001, SP7003, SP7005
     */
    public function test_night_audit_creates_run_and_all_13_steps_and_snapshots()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        Room::updateOrCreate(
            ['room_number' => '201'],
            [
                'room_class_id' => 1,
                'room_form_id' => 1,
                'floor' => '2',
                'room_status_code' => 'occupied_ready',
            ]
        );

        $booking = Booking::create([
            'booking_name' => 'KHACH DOAN CONG TY A',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'num_of_days' => 2,
            'booking_date' => $sysDateStr,
            'created_by' => 'admin',
            'registration_status_id' => 1,
        ]);

        BookingRoom::create([
            'id' => 'G2000001',
            'booking_id' => $booking->id,
            'room_class_id' => 1,
            'room_number' => '201',
            'arrival_date' => $sysDateStr,
            'departure_date' => Carbon::parse($sysDateStr)->addDays(2)->toDateString(),
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'rate' => 800000,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => false,
            'empty_to_inspect'  => false,
        ]);

        $response->assertSuccessful();

        $run = NightAuditRun::whereDate('source_system_date', $sysDateStr)->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertEquals('succeeded', $run->status);

        // Kiểm tra đúng 13 bước trong Step log
        $steps = NightAuditRunStep::where('run_id', $run->id)->orderBy('step_order')->get();
        $this->assertCount(13, $steps);

        // SP7002 và SP7004 phải ở trạng thái skipped_unconfigured
        $sp7002Step = $steps->firstWhere('step_code', 'SNAPSHOT_SP7002');
        $this->assertNotNull($sp7002Step);
        $this->assertEquals('skipped_unconfigured', $sp7002Step->status);

        $sp7004Step = $steps->firstWhere('step_code', 'SNAPSHOT_SP7004');
        $this->assertNotNull($sp7004Step);
        $this->assertEquals('skipped_unconfigured', $sp7004Step->status);

        // Các bước còn lại phải succeeded
        $succeededSteps = $steps->where('status', 'succeeded');
        $this->assertCount(11, $succeededSteps);

        // Kiểm tra các bảng snapshot có dữ liệu
        $this->assertDatabaseHas('night_audit_agency_productivity_snapshots', [
            'night_audit_run_id' => $run->id,
        ]);
        $this->assertDatabaseHas('night_audit_inhouse_snapshots', [
            'night_audit_run_id' => $run->id,
            'room'               => '201',
        ]);
        $this->assertDatabaseHas('night_audit_room_sales_forecast_snapshots', [
            'night_audit_run_id' => $run->id,
        ]);
        $this->assertDatabaseHas('night_audit_room_type_snapshots', [
            'night_audit_run_id' => $run->id,
        ]);
    }

    /**
     * Test Rollback toàn diện khi xảy ra lỗi giữa chừng trong transaction:
     * Bills không commit, ngày hệ thống không đổi, run status là failed.
     */
    public function test_night_audit_rolls_back_everything_on_failure_and_marks_run_failed()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        $initialRollCount = SystemDateRoll::count();

        // Mock service để gây lỗi cố ý tại bước SNAPSHOT_SP7005
        $mockService = \Mockery::mock(\App\Services\NightAuditSnapshotService::class)->makePartial();
        $mockService->shouldReceive('captureRoomType')
            ->once()
            ->andThrow(new \RuntimeException('Giả lập lỗi I/O CSDL khi tạo snapshot SP7005'));
        $this->app->instance(\App\Services\NightAuditSnapshotService::class, $mockService);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => false,
            'empty_to_inspect'  => false,
        ]);

        $response->assertStatus(500);

        // Run phải được ghi nhận trạng thái failed
        $failedRun = NightAuditRun::latest('id')->first();
        $this->assertNotNull($failedRun);
        $this->assertEquals('failed', $failedRun->status);
        $this->assertStringContainsString('Giả lập lỗi I/O', $failedRun->error_message);

        // Step SNAPSHOT_SP7005 phải đánh dấu failed
        $failedStep = NightAuditRunStep::where('run_id', $failedRun->id)
            ->where('step_code', 'SNAPSHOT_SP7005')
            ->first();
        $this->assertNotNull($failedStep);
        $this->assertEquals('failed', $failedStep->status);

        // Rollback: Số lượng system_date_rolls không bị tăng thêm
        $this->assertEquals($initialRollCount, SystemDateRoll::count());

        // Rollback: Không có snapshot nào bị commit dở dang
        $this->assertDatabaseMissing('night_audit_room_sales_forecast_snapshots', [
            'night_audit_run_id' => $failedRun->id,
        ]);
        $this->assertDatabaseMissing('night_audit_agency_productivity_snapshots', [
            'night_audit_run_id' => $failedRun->id,
        ]);
    }

    /**
     * Test Chống chạy đồng thời (Concurrency check - HTTP 409 Conflict)
     */
    public function test_night_audit_concurrency_blocks_simultaneous_run()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        // Giả lập một run đang chạy hợp lệ (mới bắt đầu 1 phút trước)
        NightAuditRun::create([
            'source_system_date' => $sysDateStr,
            'target_system_date' => Carbon::parse($sysDateStr)->addDay()->toDateString(),
            'actual_started_at'  => now()->subMinute(),
            'shift'              => 1,
            'username'           => 'other_user',
            'status'             => 'running',
            'idempotency_key'    => 'test_concurrency_token',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => false,
            'empty_to_inspect'  => false,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * Test Chống chạy trùng (Idempotency - HTTP 409 khi ngày này đã sang ngày thành công)
     */
    public function test_night_audit_idempotency_prevents_duplicate_runs()
    {
        $latest = SystemDateRoll::latest('id')->first();
        $sysDateStr = Carbon::parse($latest->system_date)->toDateString();

        // Giả lập một run đã hoàn tất thành công cho ngày này
        NightAuditRun::create([
            'source_system_date' => $sysDateStr,
            'target_system_date' => Carbon::parse($sysDateStr)->addDay()->toDateString(),
            'actual_started_at'  => now()->subHour(),
            'actual_finished_at' => now()->subMinutes(55),
            'shift'              => 1,
            'username'           => 'admin',
            'status'             => 'succeeded',
            'idempotency_key'    => 'test_idempotency_token',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/night-audit/run', [
            'occupied_to_dirty' => false,
            'empty_to_inspect'  => false,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * Test Bảo mật PII trong Inhouse Snapshot (toSafeArray)
     */
    public function test_inhouse_snapshot_masks_pii_for_unauthorized_users()
    {
        $snapshot = new NightAuditInhouseSnapshot([
            'guest_name' => 'NGUYEN VAN TEST',
            'passport'   => 'B1234567',
            'phone'      => '0912345678',
            'email'      => 'test@example.com',
            'address'    => '123 Đường ABC, Hà Nội',
            'room'       => '301',
        ]);

        // 1. Không có quyền xem PII -> các trường nhạy cảm phải bị che bằng '***'
        $safeArray = $snapshot->toSafeArray(canViewPii: false);
        $this->assertEquals('***', $safeArray['passport']);
        $this->assertEquals('***', $safeArray['phone']);
        $this->assertEquals('***', $safeArray['email']);
        $this->assertEquals('***', $safeArray['address']);
        $this->assertEquals('301', $safeArray['room']); // Trường không nhạy cảm giữ nguyên

        // 2. Có quyền xem PII -> giữ nguyên giá trị gốc
        $fullArray = $snapshot->toSafeArray(canViewPii: true);
        $this->assertEquals('B1234567', $fullArray['passport']);
        $this->assertEquals('0912345678', $fullArray['phone']);
        $this->assertEquals('test@example.com', $fullArray['email']);
    }
}

