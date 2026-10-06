<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelService;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\RoomClass;
use App\Models\RoomNightBill;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRoomRateServiceBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected BookingRoom $bookingRoom;
    protected ServiceBill $serviceBill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'SystemConfigurationSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentSeeder']);
        $this->artisan('db:seed', ['--class' => 'HotelDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDateRollSeeder']);
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);

        $this->user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create([
            'code' => 'test_role',
            'name' => 'Test Role',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permissionAdd = Permission::firstOrCreate(['code' => 'fo.service.add'], ['name' => 'Add service', 'module' => 'FO']);
        $permissionEdit = Permission::firstOrCreate(['code' => 'fo.service.edit'], ['name' => 'Edit service', 'module' => 'FO']);
        $role->permissions()->attach([$permissionAdd->id, $permissionEdit->id]);
        $this->user->roles()->attach($role->id);

        $roomClass = RoomClass::create([
            'name' => 'Deluxe',
            'code' => 'DLX',
            'num_of_adults' => 2,
            'num_of_children' => 1,
            'price' => 540000,
        ]);

        $status = RegistrationStatus::firstOrCreate(['id' => 1], ['name' => 'Guaranteed', 'booking_status_id' => 1]);

        $booking = Booking::create([
            'booking_name' => 'Test Guest',
            'booking_date' => Carbon::today()->toDateString(),
            'arrival_date' => Carbon::today()->toDateString(),
            'departure_date' => Carbon::today()->addDays(2)->toDateString(),
            'status' => 1,
            'registration_status_id' => $status->id,
            'created_by' => 'test_user',
        ]);

        $this->bookingRoom = BookingRoom::create([
            'id' => 'BR_TEST_001',
            'booking_id' => $booking->id,
            'room_class_id' => $roomClass->id,
            'room_number' => '105',
            'arrival_date' => Carbon::today()->toDateString(),
            'departure_date' => Carbon::today()->addDays(2)->toDateString(),
            'rate' => 540000,
            'status' => BookingRoom::STATUS_CHECKED_IN,
            'breakfast' => true,
            'adults' => 2,
        ]);

        HotelService::firstOrCreate(['code' => 'RM'], [
            'name' => 'Dịch vụ phòng nghỉ',
            'price' => 540000,
            'is_active' => true,
        ]);

        $today = Carbon::today()->toDateString();

        $this->serviceBill = ServiceBill::create([
            'Date' => $today,
            'OpenTime' => '12:00',
            'Guest' => 'Test Guest',
            'DepartmentId' => 'FO',
            'ServiceId' => 'RM',
            'DescriptionServive' => 'Dịch vụ phòng nghỉ - Phòng 105',
            'Quantity' => 1,
            'Amount' => 540000,
            'Status' => 1,
            'Edit' => 0,
            'Folio' => 1,
            'RegisterId1' => $booking->id,
            'RentalRoomId1' => $this->bookingRoom->id,
            'Username' => 'test_user',
            'CreatedUser' => 'test_user',
            'CreatedDate' => now(),
        ]);

        ServiceBillDetail::create([
            'BillServiceId' => $this->serviceBill->Ma,
            'Ma' => 1,
            'DepartmentId' => 'FO',
            'ServiceId' => 'RM',
            'DescriptionServive' => 'Dịch vụ phòng nghỉ - Phòng 105',
            'Amount' => 540000,
            'OriginalAmount' => 540000,
            'DetailBillOriginalAmount' => 540000,
        ]);

        RoomNightBill::create([
            'bill_id' => $this->serviceBill->Ma,
            'adult' => 2,
            'child' => 0,
            'is_room_night' => 1,
            'breakfast_amount' => 0,
            'date' => $today,
            'room' => '105',
            'room_type_id' => $roomClass->id,
            'rate' => 540000,
        ]);
    }

    public function test_updating_room_charge_rate_updates_service_bill_and_details(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->user)->postJson("/api/booking-rooms/{$this->bookingRoom->id}/services", [
            'booking_room_id' => $this->bookingRoom->id,
            'service_code' => 'RM',
            'service_name' => 'Dịch vụ phòng nghỉ - Phòng 105',
            'service_date' => $today,
            'quantity' => 1,
            'rate' => 500000,
            'is_room' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'service_bill' => [
                'Ma' => $this->serviceBill->Ma,
                'Amount' => '500000.00',
            ],
        ]);

        $this->serviceBill->refresh();
        $this->assertEquals(500000, (float) $this->serviceBill->Amount);

        $detail = ServiceBillDetail::where('BillServiceId', $this->serviceBill->Ma)->where('Ma', 1)->first();
        $this->assertNotNull($detail);
        $this->assertEquals(500000, (float) $detail->Amount);

        $rnb = RoomNightBill::where('bill_id', $this->serviceBill->Ma)->first();
        $this->assertNotNull($rnb);
        $this->assertEquals(500000, (float) $rnb->rate);

        $svc = BookingRoomService::where('booking_room_id', $this->bookingRoom->id)
            ->where('service_code', 'RM')
            ->whereDate('service_date', $today)
            ->first();
        $this->assertNotNull($svc);
        $this->assertEquals(500000, (float) $svc->rate);
        $this->assertEquals($this->serviceBill->Ma, $svc->service_bill_id);
        $this->assertEquals(1, $svc->is_posted);
    }

    public function test_cannot_update_room_charge_if_service_bill_already_paid(): void
    {
        $today = Carbon::today()->toDateString();

        $this->serviceBill->update(['PaymentId' => 999]);

        $response = $this->actingAs($this->user)->postJson("/api/booking-rooms/{$this->bookingRoom->id}/services", [
            'booking_room_id' => $this->bookingRoom->id,
            'service_code' => 'RM',
            'service_name' => 'Dịch vụ phòng nghỉ - Phòng 105',
            'service_date' => $today,
            'quantity' => 1,
            'rate' => 500000,
            'is_room' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        $this->serviceBill->refresh();
        $this->assertEquals(540000, (float) $this->serviceBill->Amount);
    }
}
