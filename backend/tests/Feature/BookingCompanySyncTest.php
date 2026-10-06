<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\Market;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\ServiceBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCompanySyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $companyA;
    protected Company $companyB;
    protected Company $companyC;
    protected RegistrationStatus $status;
    protected Market $market;
    protected CustomerSource $customerSource;

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

        $this->user = User::factory()->create(['username' => 'test_cashier']);
        $role = Role::create([
            'code' => 'booking_test_role',
            'name' => 'Booking Test Role',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permissionCreate = Permission::firstOrCreate(['code' => 'fo.booking.create'], ['name' => 'Create booking', 'module' => 'FO']);
        $permissionEdit = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'Edit booking', 'module' => 'FO']);
        $role->permissions()->attach([$permissionCreate->id, $permissionEdit->id]);
        $this->user->roles()->attach($role->id);

        $this->companyA = Company::create(['name' => 'Cong ty A']);
        $this->companyB = Company::create(['name' => 'Cong ty B']);
        $this->companyC = Company::create(['name' => 'Cong ty C']);

        $this->status = RegistrationStatus::where('booking_status_id', 20)->firstOrFail();
        $this->market = Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']);
        $this->customerSource = CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']);
    }

    protected function createSampleBooking(Company $company): Booking
    {
        $booking = Booking::create([
            'booking_name'           => 'DOAN KHACH ' . $company->name,
            'company_id'             => $company->id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
            'arrival_date'           => now()->toDateString(),
            'departure_date'         => now()->addDays(3)->toDateString(),
            'num_of_days'            => 3,
            'registration_status_id' => $this->status->booking_status_id,
            'status'                 => Booking::STATUS_RESERVATION,
            'booking_code'           => 'BK' . random_int(1000, 9999),
            'booking_date'           => now()->toDateString(),
            'created_by'             => $this->user->username,
            'created_by_user_id'     => $this->user->id,
        ]);

        return $booking;
    }

    public function test_updating_booking_company_cascades_to_service_bills_payments_and_sales_invoices()
    {
        $this->withoutExceptionHandling();

        $booking = $this->createSampleBooking($this->companyA);

        $physicalRoom = \App\Models\Room::first() ?? \App\Models\Room::create([
            'room_number'   => '101',
            'room_class_id' => 1,
            'status'        => 'clean',
        ]);

        $room = BookingRoom::create([
            'id'              => 'G' . str_pad((string) random_int(1, 999999), 7, '0', STR_PAD_LEFT),
            'booking_id'      => $booking->id,
            'room_class_id'   => $physicalRoom->room_class_id,
            'room_number'     => $physicalRoom->room_number,
            'arrival_date'    => $booking->arrival_date,
            'departure_date'  => $booking->departure_date,
            'NumOfDays'       => 3,
            'status'          => BookingRoom::STATUS_BOOKED,
            'price'           => 1000000,
        ]);

        // 1. Tạo ServiceBill thuộc phòng
        $billRoom = ServiceBill::create([
            'Date'               => now()->toDateTimeString(),
            'OpenTime'           => '14:00',
            'Guest'              => 'Test Guest',
            'Username'           => 'test_cashier',
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'RM',
            'DescriptionServive' => 'Tien phong 101',
            'Quantity'           => 1,
            'Amount'             => 1000000,
            'Folio'              => '1',
            'RegisterId1'        => $booking->id,
            'RentalRoomId1'      => $room->id,
            'CompanyId1'         => $this->companyA->id,
            'RegisterID2'        => $booking->id,
            'RentalRoomId2'      => $room->id,
            'CompanyId2'         => $this->companyA->id,
            'Status'             => 1,
            'Edit'               => 0,
        ]);

        // 2. Tạo ServiceBill tại Master
        $billMaster = ServiceBill::create([
            'Date'               => now()->toDateTimeString(),
            'OpenTime'           => '14:00',
            'Guest'              => 'Test Guest',
            'Username'           => 'test_cashier',
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'EB',
            'DescriptionServive' => 'Giuong phu Master',
            'Quantity'           => 1,
            'Amount'             => 300000,
            'Folio'              => '1',
            'RegisterId1'        => $booking->id,
            'RentalRoomId1'      => null,
            'CompanyId1'         => $this->companyA->id,
            'RegisterID2'        => $booking->id,
            'RentalRoomId2'      => null,
            'CompanyId2'         => $this->companyA->id,
            'Status'             => 1,
            'Edit'               => 0,
        ]);

        // 3. Tạo Payment (cọc booking & thanh toán phòng)
        $paymentBooking = Payment::create([
            'booking_id'        => $booking->id,
            'company_id'        => $this->companyA->id,
            'date'              => now()->toDateString(),
            'amount'            => 500000,
            'description'       => 'Tien dat coc',
            'payment_method_id' => 'CA',
            'folio_id'          => 1,
            'status'            => 1,
        ]);

        $paymentRoom = Payment::create([
            'booking_id'        => $booking->id,
            'booking_room_id'   => $room->id,
            'company_id'        => $this->companyA->id,
            'date'              => now()->toDateString(),
            'amount'            => 800000,
            'description'       => 'Thanh toan phong',
            'payment_method_id' => 'CA',
            'folio_id'          => 1,
            'status'            => 1,
        ]);

        // 4. Tạo SalesInvoice
        $salesInvoice = SalesInvoice::create([
            'booking_id'      => $booking->id,
            'booking_room_id' => $room->id,
            'company_id'      => $this->companyA->id,
            'invoice_date'    => now()->toDateString(),
            'payment_code'    => 'SETTLE_01',
            'amount'          => 1300000,
            'status'          => 1,
        ]);

        // Thao tác cập nhật booking sang Company B qua API PUT /api/bookings/{id}
        $updatePayload = [
            'booking_name'           => $booking->booking_name,
            'company_id'             => $this->companyB->id,
            'market_id'              => $booking->market_id,
            'customer_source_id'     => $booking->customer_source_id,
            'arrival_date'           => $booking->arrival_date,
            'departure_date'         => $booking->departure_date,
            'num_of_days'            => $booking->num_of_days,
            'registration_status_id' => $booking->registration_status_id,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/bookings/{$booking->id}", $updatePayload);

        $response->assertSuccessful();

        // 1. Kiểm tra bảng bookings
        $this->assertEquals($this->companyB->id, $booking->fresh()->company_id);

        // 2. Kiểm tra bảng payments
        $this->assertEquals($this->companyB->id, $paymentBooking->fresh()->company_id);
        $this->assertEquals($this->companyB->id, $paymentRoom->fresh()->company_id);

        // 3. Kiểm tra bảng sales_invoices
        $this->assertEquals($this->companyB->id, $salesInvoice->fresh()->company_id);

        // 4. Kiểm tra bảng service_bills
        $freshBillRoom = $billRoom->fresh();
        $this->assertEquals($this->companyB->id, $freshBillRoom->CompanyId1);
        $this->assertEquals($this->companyB->id, $freshBillRoom->CompanyId2);

        $freshBillMaster = $billMaster->fresh();
        $this->assertEquals($this->companyB->id, $freshBillMaster->CompanyId1);
        $this->assertEquals($this->companyB->id, $freshBillMaster->CompanyId2);
    }

    public function test_transferred_bill_from_other_booking_only_updates_company_id2_and_preserves_company_id1()
    {
        $this->withoutExceptionHandling();

        $bookingSource = $this->createSampleBooking($this->companyC);
        $bookingTarget = $this->createSampleBooking($this->companyA);

        // Bill được sinh ra từ bookingSource (thuộc Company C), nhưng được chuyển sang bookingTarget (thuộc Company A)
        $transferredBill = ServiceBill::create([
            'Date'               => now()->toDateTimeString(),
            'OpenTime'           => '14:00',
            'Guest'              => 'Test Guest',
            'Username'           => 'test_cashier',
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'FB',
            'DescriptionServive' => 'Nuoc suoi chuyen tu BK nguon',
            'Quantity'           => 2,
            'Amount'             => 40000,
            'Folio'              => '1',
            'RegisterId1'        => $bookingSource->id,
            'RentalRoomId1'      => null,
            'CompanyId1'         => $this->companyC->id,
            'RegisterID2'        => $bookingTarget->id,
            'RentalRoomId2'      => null,
            'CompanyId2'         => $this->companyA->id,
            'Status'             => 1,
            'Edit'               => 0,
        ]);

        // Cập nhật bookingTarget sang Company B
        $updatePayload = [
            'booking_name'           => $bookingTarget->booking_name,
            'company_id'             => $this->companyB->id,
            'market_id'              => $bookingTarget->market_id,
            'customer_source_id'     => $bookingTarget->customer_source_id,
            'arrival_date'           => $bookingTarget->arrival_date,
            'departure_date'         => $bookingTarget->departure_date,
            'num_of_days'            => $bookingTarget->num_of_days,
            'registration_status_id' => $bookingTarget->registration_status_id,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/bookings/{$bookingTarget->id}", $updatePayload);

        $response->assertSuccessful();

        $freshBill = $transferredBill->fresh();
        // CompanyId1 giữ nguyên công ty nguồn Company C
        $this->assertEquals($this->companyC->id, $freshBill->CompanyId1);
        // CompanyId2 được đổi sang công ty đích mới Company B
        $this->assertEquals($this->companyB->id, $freshBill->CompanyId2);
    }

    public function test_updating_booking_without_changing_company_keeps_company_ids_intact()
    {
        $this->withoutExceptionHandling();

        $booking = $this->createSampleBooking($this->companyA);

        $payment = Payment::create([
            'booking_id'        => $booking->id,
            'company_id'        => $this->companyA->id,
            'date'              => now()->toDateString(),
            'amount'            => 200000,
            'description'       => 'Tien dat coc giu nguyen',
            'payment_method_id' => 'CA',
            'folio_id'          => 1,
            'status'            => 1,
        ]);

        $bill = ServiceBill::create([
            'Date'               => now()->toDateTimeString(),
            'OpenTime'           => '14:00',
            'Guest'              => 'Test Guest',
            'Username'           => 'test_cashier',
            'DepartmentId'       => 'FO',
            'ServiceId'          => 'RM',
            'DescriptionServive' => 'Tien phong',
            'Quantity'           => 1,
            'Amount'             => 500000,
            'Folio'              => '1',
            'RegisterId1'        => $booking->id,
            'RentalRoomId1'      => null,
            'CompanyId1'         => $this->companyA->id,
            'RegisterID2'        => $booking->id,
            'RentalRoomId2'      => null,
            'CompanyId2'         => $this->companyA->id,
            'Status'             => 1,
            'Edit'               => 0,
        ]);

        // Cập nhật tên và ghi chú nhưng giữ nguyên Company A
        $updatePayload = [
            'booking_name'           => 'TEN MOI NGUYEN VAN B',
            'note'                   => 'Ghi chu moi',
            'company_id'             => $this->companyA->id,
            'market_id'              => $booking->market_id,
            'customer_source_id'     => $booking->customer_source_id,
            'arrival_date'           => $booking->arrival_date,
            'departure_date'         => $booking->departure_date,
            'num_of_days'            => $booking->num_of_days,
            'registration_status_id' => $booking->registration_status_id,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/bookings/{$booking->id}", $updatePayload);

        $response->assertSuccessful();

        $this->assertEquals('TEN MOI NGUYEN VAN B', $booking->fresh()->booking_name);
        $this->assertEquals($this->companyA->id, $payment->fresh()->company_id);
        $this->assertEquals($this->companyA->id, $bill->fresh()->CompanyId1);
        $this->assertEquals($this->companyA->id, $bill->fresh()->CompanyId2);
    }
}
