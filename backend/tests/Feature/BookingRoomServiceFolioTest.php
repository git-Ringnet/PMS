<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Department;
use App\Models\Guest;
use App\Models\HousekeepingServiceBill;
use App\Models\HotelService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRoomServiceFolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
    }

    public function test_folio_drag_updates_only_the_selected_room_service_and_its_linked_bill(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $sourceRoom = $this->makeRoom($booking, 'GAL1-105');
        $otherRoom = $this->makeRoom($booking, 'GAL1-106');
        $selectedBill = $this->makeBill('Dịch vụ Guest 2');
        $otherBill = $this->makeBill('Dịch vụ phòng khác');
        $selectedService = $this->makeService($sourceRoom, $selectedBill->Ma);
        $otherService = $this->makeService($otherRoom, $otherBill->Ma);

        $this->actingAs($user)
            ->patchJson("/api/booking-rooms/{$sourceRoom->id}/services/folio", [
                'service_ids' => [$selectedService->id], 'folio' => 2,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('booking_room_services', ['id' => $selectedService->id, 'folio' => 2]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $selectedBill->Ma, 'Folio' => '2']);
        $this->assertDatabaseHas('booking_room_services', ['id' => $otherService->id, 'folio' => 1]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $otherBill->Ma, 'Folio' => '1']);
    }

    public function test_folio_drag_rejects_a_service_from_another_room(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $sourceRoom = $this->makeRoom($booking, 'GAL1-105');
        $otherService = $this->makeService($this->makeRoom($booking, 'GAL1-106'), $this->makeBill('Dịch vụ khác')->Ma);

        $this->actingAs($user)
            ->patchJson("/api/booking-rooms/{$sourceRoom->id}/services/folio", [
                'service_ids' => [$otherService->id], 'folio' => 2,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('booking_room_services', ['id' => $otherService->id, 'folio' => 1]);
    }

    public function test_folio_drag_rejects_a_paid_service(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-105');
        $paidBill = $this->makeBill('Dịch vụ đã thanh toán');
        $paidBill->update(['PaymentId' => 211, 'Status' => 2]);
        $paidService = $this->makeService($room, $paidBill->Ma);

        $this->actingAs($user)
            ->patchJson("/api/booking-rooms/{$room->id}/services/folio", [
                'service_ids' => [$paidService->id], 'folio' => 2,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('booking_room_services', ['id' => $paidService->id, 'folio' => 1]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $paidBill->Ma, 'Folio' => '1']);
    }

    public function test_folio_drag_rejects_a_paid_housekeeping_bill_without_service_payment_code(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-105');
        $bill = $this->makeBill('Paid housekeeping service');
        $service = $this->makeService($room, $bill->Ma);
        HousekeepingServiceBill::create([
            'BillServiceId' => $bill->Ma, 'Status' => 2, 'Outlet' => 'MB', 'Department' => 'HK', 'Currency' => 'VND',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/booking-rooms/{$room->id}/services/folio", [
                'service_ids' => [$service->id], 'folio' => 2,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('booking_room_services', ['id' => $service->id, 'folio' => 1]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $bill->Ma, 'PaymentId' => null, 'Folio' => '1']);
    }

    public function test_front_desk_deposit_uses_dpr_without_advance_payment_code(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $user->username,
        ]);
        PaymentMethod::create(['code' => 'CA', 'name' => 'Cash', 'payment_group' => 1]);

        $this->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/payments", [
                'date' => now()->toDateString(), 'amount' => 500000, 'payment_method_id' => 'CA',
                'description' => 'Deposit (Cash)', 'pack2' => Payment::PACK2_DEPOSIT,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id, 'pack2' => Payment::PACK2_DEPOSIT, 'pack4' => null, 'amount' => 500000,
        ]);
    }

    public function test_front_desk_advance_payment_keeps_the_selected_folio(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $user->username,
        ]);
        PaymentMethod::create(['code' => 'CA', 'name' => 'Cash', 'payment_group' => 1]);

        $this->actingAs($user)
            ->postJson("/api/bookings/{$booking->id}/payments", [
                'date' => now()->toDateString(), 'amount' => 500000, 'payment_method_id' => 'CA',
                'description' => 'Advance Payment (Cash)', 'pack4' => Payment::PACK4_ADVANCE,
                'folio_id' => 3,
            ])
            ->assertSuccessful()
            ->assertJsonPath('message', 'Thanh toán trước thành công!');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id, 'folio_id' => 3, 'pack4' => Payment::PACK4_ADVANCE,
            'pack2' => null, 'amount' => 500000,
        ]);
    }

    public function test_folio_drag_moves_multiple_unused_deposits_only(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $user->username,
        ]);
        $firstDeposit = Payment::create(['booking_id' => $booking->id, 'date' => now()->toDateString(), 'amount' => 100000, 'pack2' => 'DPR', 'folio_id' => 1, 'status' => 1, 'edit_flag' => 0]);
        $secondDeposit = Payment::create(['booking_id' => $booking->id, 'date' => now()->toDateString(), 'amount' => 200000, 'pack2' => 'DPR', 'folio_id' => 1, 'status' => 1, 'edit_flag' => 0]);
        $usedDeposit = Payment::create(['booking_id' => $booking->id, 'date' => now()->toDateString(), 'amount' => 300000, 'pack2' => 'DPR', 'folio_id' => 1, 'payment_id' => 211, 'status' => 2, 'edit_flag' => 0]);

        $this->actingAs($user)
            ->patchJson("/api/payments/{$firstDeposit->id}/folio", [
                'folio_id' => 2, 'payment_ids' => [$firstDeposit->id, $secondDeposit->id],
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('payments', ['id' => $firstDeposit->id, 'folio_id' => 2]);
        $this->assertDatabaseHas('payments', ['id' => $secondDeposit->id, 'folio_id' => 2]);
        $this->assertDatabaseHas('payments', ['id' => $usedDeposit->id, 'folio_id' => 1]);
    }

    public function test_folio_drag_rejects_a_used_deposit(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => now()->toDateString(), 'departure_date' => now()->addDay()->toDateString(),
            'num_of_days' => 1, 'booking_date' => now()->toDateString(), 'created_by' => $user->username,
        ]);
        $usedDeposit = Payment::create(['booking_id' => $booking->id, 'date' => now()->toDateString(), 'amount' => 300000, 'pack2' => 'DPR', 'folio_id' => 1, 'payment_id' => 211, 'status' => 2, 'edit_flag' => 0]);

        $this->actingAs($user)
            ->patchJson("/api/payments/{$usedDeposit->id}/folio", ['folio_id' => 2])
            ->assertStatus(422);

        $this->assertDatabaseHas('payments', ['id' => $usedDeposit->id, 'folio_id' => 1]);
    }

    public function test_service_bill_details_endpoint_returns_all_invoice_lines(): void
    {
        $user = User::factory()->create();
        $bill = $this->makeBill('Minibar chuyá»ƒn phÃ²ng');
        ServiceBillDetail::create([
            'BillServiceId' => $bill->Ma, 'Ma' => 1, 'DepartmentId' => 'HK', 'ServiceId' => 'MB',
            'DescriptionServive' => 'NÆ°á»›c suá»‘i', 'OriginalRate' => 15000, 'Amount' => 30000,
        ]);
        ServiceBillDetail::create([
            'BillServiceId' => $bill->Ma, 'Ma' => 2, 'DepartmentId' => 'HK', 'ServiceId' => 'MB',
            'DescriptionServive' => 'Bia', 'OriginalRate' => 65000, 'Amount' => 65000,
        ]);

        $this->actingAs($user)
            ->getJson("/api/service-bills/{$bill->Ma}/details")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.DescriptionServive', 'NÆ°á»›c suá»‘i')
            ->assertJsonPath('data.1.DescriptionServive', 'Bia');
    }

    public function test_front_desk_service_bill_keeps_the_selected_secondary_guest(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-105');
        $roomForm = RoomForm::firstOrCreate(['name' => 'Test Form']);
        Room::create([
            'room_number' => '105',
            'room_form_id' => $roomForm->id,
            'room_class_id' => $room->room_class_id,
            'floor' => '1',
        ]);
        $room->update(['room_number' => '105']);
        $primary = Guest::create(['full_name' => 'Khach chinh']);
        $secondary = Guest::create(['full_name' => 'Khach phu']);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $primary->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'is_primary' => false, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        $service = HotelService::create([
            'code' => 'MB', 'name' => 'Minibar', 'unit' => 'Lan', 'price' => 100000,
            'service_charge' => 5, 'special_tax' => 2, 'tax' => 10, 'department_id' => $department->id,
        ]);
        $department->hotelServices()->attach($service->id, ['description' => 'Dịch vụ minibar']);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-fo-service-bill', [
                'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'date_from' => '2026-08-06',
                'date_to' => '2026-08-06', 'service_code' => 'MB', 'quantity' => 1, 'rate' => 100000,
                'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $room->id, 'RentalRoomId2' => $room->id,
            'CustomerId1' => $secondary->id, 'CustomerId2' => $secondary->id,
            'DescriptionServive' => 'Dịch vụ minibar - Phòng 105',
            'ServiceCharge' => 5, 'SpecialTax' => 2, 'Tax' => 10,
        ]);
        $bill = ServiceBill::where('RentalRoomId1', $room->id)->where('ServiceId', 'MB')->firstOrFail();
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $bill->Ma, 'ServiceCharge' => 5, 'SpecialTax' => 2, 'Tax' => 10,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'service_code' => 'MB',
            'service_charge' => 5, 'tax' => 10,
        ]);
    }

    public function test_front_desk_room_charge_keeps_the_selected_secondary_guest(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $booking->update(['is_master_room_rate' => false]);
        $room = $this->makeRoom($booking, 'GAL1-106');
        $primary = Guest::create(['full_name' => 'Khach chinh']);
        $secondary = Guest::create(['full_name' => 'Khach phu']);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $primary->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'is_primary' => false, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        HotelService::create([
            'code' => 'ER', 'name' => 'Phụ thu tiền phòng',
            'service_charge' => 6, 'special_tax' => 3, 'tax' => 9,
            'department_id' => $department->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'date_from' => '2026-08-06',
                'date_to' => '2026-08-06', 'mode' => 'surcharge', 'rate' => 100000, 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $room->id, 'RentalRoomId2' => $room->id,
            'CustomerId1' => $secondary->id, 'CustomerId2' => $secondary->id,
            'ServiceCharge' => 6, 'SpecialTax' => 3, 'Tax' => 9,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'service_code' => 'ER',
        ]);
    }

    public function test_room_charge_update_preserves_existing_room_owner_after_flag_is_enabled(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
            'is_master_room_rate' => false,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-106A');
        $guest = Guest::create(['full_name' => 'Khach phong']);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id, 'guest_id' => $guest->id,
            'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $bill = ServiceBill::create([
            'Date' => '2026-08-06', 'OpenTime' => '08:00', 'Guest' => $guest->full_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Amount' => 500000,
            'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'CustomerId1' => $guest->id,
            'RegisterID2' => $booking->id, 'RentalRoomId2' => $room->id, 'CustomerId2' => $guest->id,
            'Edit' => 0, 'Status' => 1, 'Username' => $user->username,
        ]);
        $service = BookingRoomService::create([
            'booking_room_id' => $room->id, 'guest_id' => $guest->id, 'service_bill_id' => $bill->Ma,
            'service_code' => 'RM', 'service_date' => '2026-08-06', 'quantity' => 1,
            'rate' => 500000, 'folio' => 1, 'is_room' => 1, 'is_posted' => 1,
        ]);
        $booking->update(['is_master_room_rate' => true]);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_room_id' => $room->id, 'guest_id' => $guest->id,
                'date_from' => '2026-08-06', 'date_to' => '2026-08-06',
                'mode' => 'update', 'rate' => 550000, 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'Ma' => $bill->Ma, 'Amount' => 550000,
            'RentalRoomId2' => $room->id, 'CustomerId2' => $guest->id,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'id' => $service->id, 'service_bill_id' => $bill->Ma, 'is_room' => 1,
        ]);
        $this->assertSame(1, ServiceBill::where('ServiceId', 'RM')->count());
    }

    public function test_front_desk_git_service_is_owned_by_master_while_preserving_source_room(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-107');
        $guest = Guest::create(['full_name' => 'Khach GIT']);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id, 'guest_id' => $guest->id,
            'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        HotelService::create(['code' => 'MB', 'name' => 'Minibar', 'unit' => 'Lan', 'price' => 100000, 'department_id' => $department->id]);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-fo-service-bill', [
                'booking_room_id' => $room->id, 'guest_id' => $guest->id,
                'date_from' => '2026-08-06', 'date_to' => '2026-08-06',
                'service_code' => 'MB', 'quantity' => 1, 'rate' => 100000,
                'folio' => 1, 'is_room' => 0, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RegisterId1' => $booking->id, 'RentalRoomId1' => $room->id, 'CustomerId1' => $guest->id,
            'RegisterID2' => $booking->id, 'RentalRoomId2' => null, 'CustomerId2' => null,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id, 'guest_id' => $guest->id, 'service_code' => 'MB', 'is_room' => 0,
        ]);
    }

    public function test_master_auto_room_charge_posts_only_inhouse_rooms_with_assigned_room_numbers(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $booking->update(['is_master_room_rate' => true]);
        $inhouseRoom = $this->makeRoom($booking, 'GAL1-101');
        $reservedRoom = $this->makeRoom($booking, 'GAL1-102');
        $unassignedRoom = $this->makeRoom($booking, 'GAL1-UNASSIGNED');
        $roomForm = RoomForm::firstOrCreate(['name' => 'Test Form']);
        Room::create([
            'room_number' => '101',
            'room_form_id' => $roomForm->id,
            'room_class_id' => $inhouseRoom->room_class_id,
            'floor' => '1',
        ]);
        Room::create([
            'room_number' => '102',
            'room_form_id' => $roomForm->id,
            'room_class_id' => $reservedRoom->room_class_id,
            'floor' => '1',
        ]);
        BookingRoom::withoutEvents(function () use ($inhouseRoom, $reservedRoom, $unassignedRoom) {
            $inhouseRoom->update(['room_number' => '101', 'status' => BookingRoom::STATUS_CHECKED_IN, 'rate' => 500000]);
            $reservedRoom->update(['room_number' => '102', 'status' => BookingRoom::STATUS_BOOKED, 'rate' => 500000]);
            $unassignedRoom->update(['status' => BookingRoom::STATUS_CHECKED_IN, 'rate' => 500000]);
        });
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        HotelService::create([
            'code' => 'RM', 'name' => 'Dịch vụ phòng nghỉ',
            'service_charge' => 5, 'special_tax' => 1, 'tax' => 8,
            'department_id' => $department->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_id' => $booking->id,
                'date_from' => '2026-08-06',
                'date_to' => '2026-08-06',
                'mode' => 'auto',
                'folio' => 1,
                'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $inhouseRoom->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
            'ServiceId' => 'RM',
            'ServiceCharge' => 5,
            'SpecialTax' => 1,
            'Tax' => 8,
            'Edit' => 0,
        ]);
        $this->assertDatabaseMissing('service_bills', ['RentalRoomId1' => $reservedRoom->id, 'ServiceId' => 'RM', 'Edit' => 0]);
        $this->assertDatabaseMissing('service_bills', ['RentalRoomId1' => $unassignedRoom->id, 'ServiceId' => 'RM', 'Edit' => 0]);
    }

    public function test_room_charge_posts_pending_booking_services_for_selected_dates_once(): void
    {
        $user = User::factory()->create();
        $user->update(['name' => 'Test User', 'employee_code' => 'NV001']);
        SystemDateRoll::create([
            'system_date' => '2026-08-06', 'actual_date' => now(), 'shift' => '1', 'username' => $user->username,
        ]);
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-08',
            'num_of_days' => 2, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $booking->update(['is_master_room_rate' => false]);
        $room = $this->makeRoom($booking, 'GAL1-109');
        $room->update(['departure_date' => '2026-08-08', 'rate' => 500000]);
        $guest = Guest::create(['full_name' => 'Khach chinh']);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id, 'guest_id' => $guest->id,
            'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception']);
        $minibar = HotelService::create(['code' => 'MB', 'name' => 'Minibar', 'price' => 100000, 'department_id' => $department->id]);
        $childBreakfast = HotelService::create(['code' => 'BD', 'name' => 'Child breakfast surcharge', 'price' => 80000, 'department_id' => $department->id]);
        $breakfast = HotelService::create(['code' => 'BF', 'name' => 'Breakfast', 'price' => 50000, 'department_id' => $department->id]);
        $department->hotelServices()->attach([
            $minibar->id => ['description' => 'Minibar'],
            $childBreakfast->id => ['description' => 'Phụ thu ăn sáng trẻ em'],
            $breakfast->id => ['description' => 'Ăn sáng'],
        ]);
        $fitService = BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_code' => 'MB', 'service_name' => 'Minibar',
            'service_date' => '2026-08-06', 'quantity' => 2, 'rate' => 100000, 'folio' => 2,
            'is_room' => 1, 'is_posted' => 0,
        ]);
        $gitService = BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_code' => 'BD', 'service_name' => 'Child breakfast surcharge',
            'service_date' => '2026-08-06', 'quantity' => 1, 'rate' => 80000, 'folio' => 3,
            'is_room' => 0, 'is_posted' => 0, 'note' => 'Phụ thu ăn sáng trẻ em: Child 1',
        ]);
        $outsideRange = BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_code' => 'BF', 'service_name' => 'Breakfast',
            'service_date' => '2026-08-07', 'quantity' => 1, 'rate' => 50000, 'folio' => 1,
            'is_room' => 1, 'is_posted' => 0,
        ]);

        $payload = [
            'booking_room_id' => $room->id, 'date_from' => '2026-08-06', 'date_to' => '2026-08-06',
            'mode' => 'auto', 'folio' => 1, 'currency' => 'VND',
        ];

        $this->actingAs($user)->postJson('/api/booking-room-services/post-room-charge', $payload)->assertSuccessful();
        $this->actingAs($user)->postJson('/api/booking-room-services/post-room-charge', $payload)->assertSuccessful();

        $fitService->refresh();
        $gitService->refresh();
        $outsideRange->refresh();

        $this->assertSame(1, (int) $fitService->is_posted);
        $this->assertNotNull($fitService->service_bill_id);
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $fitService->service_bill_id, 'ServiceId' => 'MB', 'Amount' => 200000,
            'Folio' => '2', 'RentalRoomId2' => $room->id, 'CustomerId2' => $guest->id,
            'employee_code' => 'NV001', 'Ca' => '1',
        ]);
        $this->assertSame('Test User', ServiceBill::findOrFail($fitService->service_bill_id)->employeeOperator->name);
        $this->assertSame('NV001', $fitService->posted_by_employee_code);
        $this->assertSame(1, (int) $gitService->is_posted);
        $this->assertNotNull($gitService->service_bill_id);
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $gitService->service_bill_id, 'ServiceId' => 'BD', 'Amount' => 80000,
            'Folio' => '3', 'RegisterID2' => $booking->id, 'RentalRoomId2' => null, 'CustomerId2' => null,
            'DescriptionServive' => 'Phụ thu ăn sáng trẻ em - Child 1',
        ]);
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $gitService->service_bill_id,
            'ServiceId' => 'BD',
            'DescriptionServive' => 'Phụ thu ăn sáng trẻ em - Child 1',
        ]);
        $this->assertSame(0, (int) $outsideRange->is_posted);
        $this->assertNull($outsideRange->service_bill_id);
        $this->assertSame(1, ServiceBill::where('ServiceId', 'MB')->count());
        $this->assertSame(1, ServiceBill::where('ServiceId', 'BD')->count());
        $this->actingAs($user)
            ->getJson('/api/bookings?with_billing=true')
            ->assertSuccessful()
            ->assertJsonFragment(['employee_code' => 'NV001', 'name' => 'Test User']);
    }

    public function test_quick_transfer_from_master_keeps_the_negative_audit_line(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $booking->update(['is_master_room_rate' => true]);
        $targetRoom = $this->makeRoom($booking, 'GAL1-108');
        $guest = Guest::create(['full_name' => 'Khach nhan']);
        BookingRoomGuest::create([
            'booking_room_id' => $targetRoom->id, 'guest_id' => $guest->id,
            'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);
        $sourceBill = $this->makeBill('Dich vu tai Master');
        $sourceBill->update([
            'ServiceId' => 'RM',
            'RegisterId1' => $booking->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId2' => null,
            'CustomerId2' => null,
            'Amount' => 100000,
            'Quantity' => 1,
            'Status' => 1,
            'Edit' => 0,
        ]);

        $this->actingAs($user)
            ->postJson("/api/booking-rooms/{$targetRoom->id}/services/quick-transfer", [
                'bill_ids' => [$sourceBill->Ma],
                'target_guest_id' => $guest->id,
            ])
            ->assertSuccessful();

        $sourceBill->refresh();
        $positiveBill = ServiceBill::where('RentalRoomId2', $targetRoom->id)
            ->where('Edit', 0)
            ->where('Status', 1)
            ->firstOrFail();
        $negativeBill = ServiceBill::where('RegisterID2', $booking->id)
            ->where('Amount', -100000)
            ->where('Edit', 1)
            ->where('Status', 4)
            ->firstOrFail();

        $this->assertSame(1, (int) $sourceBill->Edit);
        $this->assertSame(4, (int) $sourceBill->Status);
        $this->assertTrue((bool) $booking->fresh()->is_master_room_rate);
        $this->assertSame((string) $positiveBill->Ma, (string) $negativeBill->Pack1);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $targetRoom->id,
            'guest_id' => $guest->id,
            'service_bill_id' => $positiveBill->Ma,
            'folio' => 1,
        ]);
    }

    public function test_quick_transfer_candidates_group_by_bill_service_and_use_fo_description(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $sourceRoom = $this->makeRoom($booking, 'GAL1-106');
        $targetRoom = $this->makeRoom($booking, 'GAL1-108');
        $department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception']);
        $childBreakfast = HotelService::create([
            'code' => 'BD', 'name' => 'Child breakfast', 'price' => 90000, 'department_id' => $department->id,
        ]);
        $extraBed = HotelService::create([
            'code' => 'EB', 'name' => 'Extra bed', 'price' => 200000, 'department_id' => $department->id,
        ]);
        $department->hotelServices()->attach([
            $childBreakfast->id => ['description' => 'Phụ thu ăn sáng trẻ em'],
            $extraBed->id => ['description' => 'Phụ thu thêm giường'],
        ]);

        $roomBill = ServiceBill::create([
            'Date' => '2026-08-06 00:00:00', 'OpenTime' => '08:00', 'Guest' => 'Guest 1',
            'DepartmentId' => 'FO', 'Outlet' => 'FO', 'ServiceId' => 'BD',
            'DescriptionServive' => 'Phụ thu ăn sáng trẻ em - Child 1',
            'RegisterID2' => $booking->id, 'RentalRoomId2' => $sourceRoom->id,
            'Amount' => 90000, 'Status' => 1, 'Edit' => 0, 'Username' => 'test',
        ]);
        BookingRoomService::create([
            'booking_room_id' => $sourceRoom->id, 'service_bill_id' => $roomBill->Ma,
            'service_code' => 'WRONG_SOURCE_CODE', 'service_date' => '2026-08-06',
            'quantity' => 1, 'rate' => 90000, 'folio' => 1,
        ]);
        ServiceBill::create([
            'Date' => '2026-08-06 00:00:00', 'OpenTime' => '09:00', 'Guest' => 'Master',
            'DepartmentId' => 'FO', 'Outlet' => 'FO', 'ServiceId' => 'EB',
            'DescriptionServive' => 'Phụ thu thêm giường - Phòng 106',
            'RegisterID2' => $booking->id, 'RentalRoomId2' => null,
            'Amount' => 200000, 'Status' => 1, 'Edit' => 0, 'Username' => 'test',
        ]);

        $this->actingAs($user)
            ->getJson("/api/booking-rooms/{$targetRoom->id}/services/quick-transfer-candidates")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'bill_id' => $roomBill->Ma,
                'category' => 'BD',
                'category_label' => 'Phụ thu ăn sáng trẻ em',
            ])
            ->assertJsonFragment([
                'category' => 'EB',
                'category_label' => 'Phụ thu thêm giường',
            ])
            ->assertJsonMissing(['category' => 'FO']);
    }

    public function test_fo_service_list_only_returns_services_assigned_to_fo(): void
    {
        $user = User::factory()->create();
        $fo = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception']);
        $fb = Department::firstOrCreate(['code' => 'FB'], ['name' => 'Restaurant']);
        $foService = HotelService::create([
            'code' => 'FO-SVC', 'name' => 'Dich vu FO', 'price' => 100000, 'department_id' => $fo->id,
        ]);
        $fbService = HotelService::create([
            'code' => 'FB-SVC', 'name' => 'Dich vu FB', 'price' => 200000, 'department_id' => $fb->id,
        ]);
        $sharedService = HotelService::create([
            'code' => 'SHARED', 'name' => 'Dich vu dung chung', 'price' => 300000, 'department_id' => $fb->id,
        ]);
        $fo->hotelServices()->attach([$foService->id, $sharedService->id]);
        $fb->hotelServices()->attach([$fbService->id, $sharedService->id]);

        $this->actingAs($user)
            ->getJson('/api/booking-services/fo-list')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['code' => 'FO-SVC'])
            ->assertJsonFragment(['code' => 'SHARED'])
            ->assertJsonMissing(['code' => 'FB-SVC']);
    }

    public function test_housekeeping_bill_uses_each_product_tax_profile_from_database(): void
    {
        $user = User::factory()->create();
        SystemDateRoll::create([
            'system_date' => '2026-08-06', 'actual_date' => now(), 'shift' => '1', 'username' => $user->username,
        ]);
        $booking = Booking::create([
            'booking_name' => 'GAL1', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $user->username,
        ]);
        $room = $this->makeRoom($booking, 'GAL1-HK');
        $category = ProductCategory::create(['name' => 'Minibar', 'outlet' => 'MB']);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Nước suối',
            'price' => 100000,
            'service_charge_percent' => 5,
            'special_tax_percent' => 2,
            'tax_percent' => 10,
            'open_key' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-housekeeping-bill', [
                'booking_room_id' => $room->id,
                'posting_source' => 'HK',
                'service_date' => '2026-08-06',
                'folio' => 1,
                'bills' => [[
                    'group' => 'MB',
                    'items' => [[
                        'id' => $product->id,
                        'name' => $product->name,
                        'qty' => 1,
                        'price' => 100000,
                        'original_rate' => 100000,
                        'net_price' => 100000,
                        'total_amount' => 100000,
                        'service_charge' => 0,
                        'special_tax' => 0,
                        'tax' => 0,
                    ]],
                ]],
            ])
            ->assertSuccessful();

        $bill = ServiceBill::where('RentalRoomId1', $room->id)->where('ServiceId', 'MB')->firstOrFail();
        $this->assertDatabaseHas('service_bills', [
            'Ma' => $bill->Ma, 'ServiceCharge' => 5, 'SpecialTax' => 2, 'Tax' => 10,
        ]);
        $this->assertDatabaseHas('service_bill_details', [
            'BillServiceId' => $bill->Ma, 'ServiceCharge' => 5, 'SpecialTax' => 2, 'Tax' => 10,
        ]);
        $this->assertDatabaseHas('housekeeping_service_bills', [
            'BillServiceId' => $bill->Ma, 'BillServicesCharge' => 5, 'BillSpecialTax' => 2, 'BillTax' => 10,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'service_bill_id' => $bill->Ma, 'service_charge' => 5, 'tax' => 10,
        ]);
    }

    private function makeRoom(Booking $booking, string $id): BookingRoom
    {
        $roomClass = RoomClass::firstOrCreate(['code' => 'TEST'], ['name' => 'Test Room']);

        return BookingRoom::create([
            'id' => $id, 'booking_id' => $booking->id, 'room_class_id' => $roomClass->id,
            'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07', 'rate' => 0,
        ]);
    }

    private function makeBill(string $description): ServiceBill
    {
        return ServiceBill::create([
            'Date' => '2026-08-06 00:00:00', 'OpenTime' => '00:00', 'Guest' => 'Guest 2',
            'DepartmentId' => 'HK', 'ServiceId' => 'MB', 'DescriptionServive' => $description, 'Username' => 'test',
        ]);
    }

    private function makeService(BookingRoom $room, int $billId): BookingRoomService
    {
        return BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_bill_id' => $billId, 'service_code' => 'MB',
            'service_date' => '2026-08-06', 'quantity' => 1, 'rate' => 100000, 'folio' => 1,
        ]);
    }
}
