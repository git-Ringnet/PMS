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
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Models\PaymentMethod;
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

    public function test_room_checkout_moves_only_unpaid_bills_to_master(): void
    {
        $base = ['Date' => '2026-08-04', 'OpenTime' => '12:00', 'Guest' => $this->guest->full_name, 'DepartmentId' => 'FO', 'ServiceId' => 'MB', 'Username' => 'checkout_rules_user', 'Edit' => 0, 'RegisterId1' => $this->booking->id, 'RentalRoomId1' => $this->room->id, 'RegisterID2' => $this->booking->id, 'RentalRoomId2' => $this->room->id];
        $unpaid = ServiceBill::create([...$base, 'Status' => 1]);
        $paid = ServiceBill::create([...$base, 'Status' => 2, 'PaymentId' => 1]);

        $this->postJson("/api/booking-rooms/{$this->room->id}/checkout", ['guest_ids' => [$this->guest->id], 'skip_remaining_room_charge' => true])->assertSuccessful();

        $this->assertDatabaseHas('service_bills', ['Ma' => $unpaid->Ma, 'RegisterID2' => $this->booking->id, 'RentalRoomId2' => null]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $paid->Ma, 'RentalRoomId2' => $this->room->id, 'PaymentId' => 1]);
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKIN]);

        PaymentMethod::create(['code' => 'CA', 'name' => 'Tiền mặt', 'payment_group' => 1]);
        $this->postJson("/api/bookings/{$this->booking->id}/settle-payment", [
            'folio_id' => 1,
            'payments' => [['amount' => 1, 'payment_method_id' => 'CA']],
        ])->assertSuccessful();
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => Booking::STATUS_CHECKOUT]);
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
