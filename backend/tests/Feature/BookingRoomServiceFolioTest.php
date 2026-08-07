<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HousekeepingServiceBill;
use App\Models\HotelService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
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
        $primary = Guest::create(['full_name' => 'Khach chinh']);
        $secondary = Guest::create(['full_name' => 'Khach phu']);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $primary->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'is_primary' => false, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        HotelService::create(['code' => 'MB', 'name' => 'Minibar', 'unit' => 'Lan', 'price' => 100000, 'department' => 'FO']);

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
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'service_code' => 'MB',
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

        $this->actingAs($user)
            ->postJson('/api/booking-room-services/post-room-charge', [
                'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'date_from' => '2026-08-06',
                'date_to' => '2026-08-06', 'mode' => 'surcharge', 'rate' => 100000, 'folio' => 1, 'currency' => 'VND',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('service_bills', [
            'RentalRoomId1' => $room->id, 'RentalRoomId2' => $room->id,
            'CustomerId1' => $secondary->id, 'CustomerId2' => $secondary->id,
        ]);
        $this->assertDatabaseHas('booking_room_services', [
            'booking_room_id' => $room->id, 'guest_id' => $secondary->id, 'service_code' => 'RMS',
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
            'Edit' => 0,
        ]);
        $this->assertDatabaseMissing('service_bills', ['RentalRoomId1' => $reservedRoom->id, 'ServiceId' => 'RM', 'Edit' => 0]);
        $this->assertDatabaseMissing('service_bills', ['RentalRoomId1' => $unassignedRoom->id, 'ServiceId' => 'RM', 'Edit' => 0]);
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
