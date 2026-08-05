<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\Room;
use App\Models\ServiceBill;
use App\Models\ServiceBillDetail;
use App\Models\HotelSetting;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private BookingRoom $room;
    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['username' => 'checkout_rules_user']));
        DB::table('booking_statuses')->insert([
            ['id' => 0, 'name' => 'Reservation'], ['id' => 1, 'name' => 'Checked In'], ['id' => 2, 'name' => 'Checked Out'],
        ]);
        SystemDateRoll::create(['system_date' => '2026-08-04', 'actual_date' => '2026-08-04', 'shift' => '1', 'username' => 'checkout_rules_user']);
        $form = RoomForm::create(['name' => 'Standard']);
        $class = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        Room::create(['room_number' => '101', 'room_form_id' => $form->id, 'room_class_id' => $class->id, 'floor' => 1, 'status' => 'occupied']);
        $this->booking = Booking::create(['booking_name' => 'Checkout rules', 'booking_date' => '2026-08-01', 'arrival_date' => '2026-08-01', 'departure_date' => '2026-08-06', 'status' => Booking::STATUS_CHECKIN, 'created_by' => 'checkout_rules_user']);
        $this->room = BookingRoom::create(['booking_id' => $this->booking->id, 'room_number' => '101', 'room_class_id' => $class->id, 'arrival_date' => '2026-08-01', 'departure_date' => '2026-08-06', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->guest = Guest::create(['full_name' => 'Guest checkout']);
        BookingRoomGuest::create(['booking_room_id' => $this->room->id, 'guest_id' => $this->guest->id, 'status' => BookingRoomGuest::STATUS_CHECKED_IN, 'is_primary' => true]);
        HotelConfig::create(['name' => 'AllowEarlyCheckout', 'value' => '1', 'description' => 'Allow early checkout']);
        HotelSetting::create(['hotel_name' => 'Checkout test hotel', 'breakfast_adult_rate' => 50000]);
    }

    public function test_master_checkout_ignores_room_departure_date(): void
    {
        $this->postJson("/api/bookings/{$this->booking->id}/checkout")->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKOUT]);
        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'status' => BookingRoom::STATUS_CHECKED_OUT]);
    }

    public function test_early_room_checkout_requires_remaining_nights_to_be_charged(): void
    {
        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", ['guest_ids' => [$this->guest->id]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'early_checkout');

        foreach (['2026-08-04', '2026-08-05'] as $date) {
            ServiceBill::create([
                'Date' => $date, 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name,
                'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Username' => 'checkout_rules_user',
                'Edit' => 0, 'Status' => 2, 'PaymentId' => 1,
                'RegisterId1' => $this->booking->id, 'RentalRoomId1' => $this->room->id,
            ]);
        }

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", ['guest_ids' => [$this->guest->id]])->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'status' => BookingRoom::STATUS_CHECKED_OUT, 'CheckoutDate' => '2026-08-04 00:00:00']);
    }

    public function test_early_room_checkout_can_proceed_without_charge_after_confirmation(): void
    {
        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", [
            'guest_ids' => [$this->guest->id],
            'skip_remaining_room_charge' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'status' => BookingRoom::STATUS_CHECKED_OUT]);
    }

    public function test_room_checkout_is_blocked_when_it_has_unpaid_bills(): void
    {
        $base = ['Date' => '2026-08-04', 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name, 'DepartmentId' => 'FO', 'ServiceId' => 'MB', 'Username' => 'checkout_rules_user', 'Edit' => 0, 'RegisterId1' => $this->booking->id, 'RentalRoomId1' => $this->room->id, 'RegisterID2' => $this->booking->id, 'RentalRoomId2' => $this->room->id];
        $unpaid = ServiceBill::create([...$base, 'Status' => 1]);
        $paid = ServiceBill::create([...$base, 'Status' => 2, 'PaymentId' => 1]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", ['guest_ids' => [$this->guest->id], 'skip_remaining_room_charge' => true])
            ->assertStatus(422)
            ->assertJsonPath('code', 'unpaid_bill');

        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $unpaid->Ma, 'RentalRoomId2' => $this->room->id]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $paid->Ma, 'RentalRoomId2' => $this->room->id, 'PaymentId' => 1]);
    }

    public function test_room_checkout_allows_unpaid_room_charge_when_room_rate_is_sent_to_master(): void
    {
        $this->booking->update(['is_master_room_rate' => true]);
        ServiceBill::create([
            'Date' => '2026-08-04', 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Username' => 'checkout_rules_user',
            'Edit' => 0, 'Status' => 1, 'RegisterId1' => $this->booking->id,
            'RentalRoomId1' => $this->room->id, 'RentalRoomId2' => $this->room->id,
        ]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", [
            'guest_ids' => [$this->guest->id],
            'skip_remaining_room_charge' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'status' => BookingRoom::STATUS_CHECKED_OUT]);
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKIN]);
    }

    public function test_room_checkout_reopens_booking_when_unpaid_master_room_charge_remains(): void
    {
        $this->booking->update(['status' => Booking::STATUS_CHECKOUT, 'is_master_room_rate' => true]);
        ServiceBill::create([
            'Date' => '2026-08-04', 'OpenTime' => '12:00', 'Guest' => $this->booking->booking_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Username' => 'checkout_rules_user',
            'Edit' => 0, 'Status' => 1, 'RegisterId1' => $this->booking->id,
            'RentalRoomId1' => $this->room->id, 'RegisterID2' => $this->booking->id,
            'RentalRoomId2' => $this->room->id,
        ]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", [
            'guest_ids' => [$this->guest->id],
            'skip_remaining_room_charge' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKIN]);
    }

    public function test_master_can_adjust_an_unpaid_room_charge_with_audit_bills(): void
    {
        $this->room->update(['adults' => 2, 'breakfast' => true]);
        $original = ServiceBill::create([
            'Date' => '2026-08-03', 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Amount' => 650000, 'Username' => 'checkout_rules_user',
            'Edit' => 0, 'Status' => 1, 'RegisterId1' => $this->booking->id, 'RegisterID2' => $this->booking->id,
            'RentalRoomId1' => $this->room->id, 'RentalRoomId2' => $this->room->id,
        ]);

        $this->postJson("/api/bookings/{$this->booking->id}/adjust-room-rate", [
            'booking_room_id' => $this->room->id, 'service_date' => '2026-08-03', 'rate' => 700000,
            'reason' => 'Sửa giá hợp đồng', 'update_room_rate' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('service_bills', ['Ma' => $original->Ma, 'Edit' => 1, 'Status' => 3, 'IsAdjustment' => 1]);
        $this->assertDatabaseHas('service_bills', ['AdjustmentBillId' => $original->Ma, 'Amount' => -650000, 'Edit' => 1, 'Status' => 3, 'IsAdjustment' => 1]);
        $this->assertDatabaseHas('service_bills', ['AdjustmentBillId' => $original->Ma, 'Amount' => 700000, 'Edit' => 0, 'Status' => 1, 'IsAdjustment' => 1]);
        $this->assertDatabaseHas('booking_rooms', ['id' => $this->room->id, 'rate' => 700000]);
        $adjustment = ServiceBill::where('AdjustmentBillId', $original->Ma)->where('Edit', 0)->firstOrFail();
        $this->assertSame(3, ServiceBillDetail::where('BillServiceId', $adjustment->Ma)->count());
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $adjustment->Ma, 'Ma' => 2, 'ServiceId' => 'BF', 'Amount' => 100000]);
        $this->assertDatabaseHas('service_bill_details', ['BillServiceId' => $adjustment->Ma, 'Ma' => 3, 'ServiceId' => 'RM', 'Amount' => -100000]);
    }

    public function test_early_checkout_returns_only_dates_without_room_charge(): void
    {
        ServiceBill::create([
            'Date' => '2026-08-04', 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name,
            'DepartmentId' => 'FO', 'ServiceId' => 'RM', 'Username' => 'checkout_rules_user',
            'Edit' => 0, 'Status' => 2, 'PaymentId' => 1,
            'RegisterId1' => $this->booking->id, 'RentalRoomId1' => $this->room->id,
        ]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", ['guest_ids' => [$this->guest->id]])
            ->assertStatus(422)
            ->assertJsonPath('data.remaining_dates', ['2026-08-05']);
    }

    public function test_child_can_checkout_separately_while_an_adult_remains(): void
    {
        $child = BookingChild::create([
            'booking_id' => $this->booking->id, 'booking_room_id' => $this->room->id,
            'full_name' => 'Child checkout', 'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
        ]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/children/{$child->id}/checkout")->assertSuccessful();
        $this->assertDatabaseHas('booking_children', ['id' => $child->id, 'child_status' => BookingRoomGuest::STATUS_CHECKED_OUT]);
    }
}
