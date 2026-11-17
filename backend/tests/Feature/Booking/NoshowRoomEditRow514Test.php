<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\HotelSetting;
use App\Models\Market;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\ServiceBill;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoshowRoomEditRow514Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RoomForm $roomForm;
    private RegistrationStatus $registrationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'noshow514_user']);
        $role = Role::create([
            'code' => 'noshow514_test',
            'name' => 'Allocation consistency test',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        foreach (['fo.booking.create', 'fo.booking.edit'] as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'FO']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => 0, 'name' => 'Reservation'],
            ['id' => 1, 'name' => 'Checked In'],
            ['id' => 2, 'name' => 'Checked Out'],
            ['id' => 3, 'name' => 'Cancelled'],
            ['id' => 4, 'name' => 'No Show'],
            ['id' => 100, 'name' => 'Moved'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-11-07 00:00:00',
            'actual_date' => '2026-11-07 00:00:00',
            'shift' => '1',
            'username' => 'noshow514_user',
        ]);

        $this->roomForm = RoomForm::create(['name' => 'Standard Form']);
        $this->roomClass = RoomClass::create([
            'code' => 'STD',
            'name' => 'Standard Class',
            'is_active' => true,
        ]);
        for ($number = 101; $number <= 108; $number++) {
            $this->createPhysicalRoom((string) $number, $this->roomClass);
        }

        $this->registrationStatus = RegistrationStatus::create([
            'name' => 'Guaranteed',
            'booking_status_id' => 1,
            'is_availability' => true,
        ]);

        Market::create(['id' => 1, 'name' => 'FIT', 'code' => 'FIT']);
        CustomerSource::create(['id' => 1, 'name' => 'WalkIn', 'code' => 'WI']);
        Company::create(['id' => 1, 'name' => 'WalkIn Company', 'code' => 'WIC']);
        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0']);
        HotelConfig::create(['name' => 'FrmOOO_DefineLockByTime', 'value' => '23:59']);
        HotelSetting::create([
            'hotel_name' => 'Allocation Test Hotel',
            'breakfast_child_rate' => 90000,
            'booking_auto_extra_charge_bf_child' => 1,
        ]);
    }

    private function createPhysicalRoom(string $number, RoomClass $roomClass): Room
    {
        return Room::create([
            'room_number' => $number,
            'room_class_id' => $roomClass->id,
            'room_form_id' => $this->roomForm->id,
            'floor' => 1,
            'status' => 'available',
        ]);
    }

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Allocation Test Booking',
            'arrival_date' => '2026-11-06',
            'departure_date' => '2026-11-08',
            'booking_date' => '2026-11-06',
            'created_by' => 'noshow514_user',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
        ], $attributes));
    }

    private function updatePayload(Booking $booking, array $allocations): array
    {
        return [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-11-06',
            'departure_date' => '2026-11-08',
            'num_of_days' => 2,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => $allocations,
        ];
    }

    private function noshowRoom(Booking $booking, string $arrival = '2026-11-06', string $departure = '2026-11-08'): BookingRoom
    {
        return BookingRoom::create([
            'id' => 'BR' . uniqid(), 'booking_id' => $booking->id, 'room_number' => '101',
            'room_class_id' => $this->roomClass->id, 'arrival_date' => $arrival, 'departure_date' => $departure,
            'rate' => 1000000, 'adults' => 1, 'status' => BookingRoom::STATUS_NOSHOW,
        ]);
    }

    private function noshowBooking(): array
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_NO_SHOW, 'num_of_days' => 2]);
        return [$booking, $this->noshowRoom($booking)];
    }

    // Dòng 514: phòng noshow phải sửa được (ngày đến/đi, số phòng, giá, khách) và vẫn là noshow.
    public function test_noshow_room_edit_is_saved_and_room_stays_noshow(): void
    {
        [$booking, $room] = $this->noshowBooking();
        $payload = $this->updatePayload($booking, []);
        $payload['noshow_rooms'] = [[
            'bookingRoomId' => $room->id, 'arrivalDate' => '2026-11-07', 'departureDate' => '2026-11-08',
            'roomNumber' => '102', 'price' => 1200000, 'adults' => 2, 'guestName' => 'Khach tre',
        ]];

        $this->putJson("/api/bookings/{$booking->id}", $payload)->assertOk();

        $room->refresh();
        $this->assertSame('2026-11-07', $room->arrival_date->toDateString());
        $this->assertSame('2026-11-08', $room->departure_date->toDateString());
        $this->assertSame('102', $room->room_number);
        $this->assertEquals(1200000, $room->rate);
        $this->assertSame(2, (int) $room->adults);
        $this->assertSame(BookingRoom::STATUS_NOSHOW, (int) $room->status);
    }

    // Dòng 514: sau khi sửa ngày đến về ngày hệ thống thì khôi phục noshow được.
    public function test_noshow_room_can_be_restored_after_arrival_date_is_edited(): void
    {
        [$booking, $room] = $this->noshowBooking();
        $this->postJson("/api/bookings/{$booking->id}/rooms/{$room->id}/revert-noshow")
            ->assertStatus(422);

        $payload = $this->updatePayload($booking, []);
        $payload['noshow_rooms'] = [['bookingRoomId' => $room->id, 'arrivalDate' => '2026-11-07', 'departureDate' => '2026-11-08']];
        $this->putJson("/api/bookings/{$booking->id}", $payload)->assertOk();

        $this->postJson("/api/bookings/{$booking->id}/rooms/{$room->id}/revert-noshow")
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $room->fresh()->status);
    }

    public function test_noshow_room_edit_rejects_departure_before_arrival(): void
    {
        [$booking, $room] = $this->noshowBooking();
        $payload = $this->updatePayload($booking, []);
        $payload['noshow_rooms'] = [['bookingRoomId' => $room->id, 'arrivalDate' => '2026-11-09', 'departureDate' => '2026-11-08']];

        $this->putJson("/api/bookings/{$booking->id}", $payload)->assertStatus(422);
        $this->assertSame('2026-11-06', $room->fresh()->arrival_date->toDateString());
    }

    public function test_noshow_rooms_payload_cannot_touch_non_noshow_or_foreign_rooms(): void
    {
        [$booking, $room] = $this->noshowBooking();
        $other = $this->createBooking(['num_of_days' => 2]);
        $foreign = $this->noshowRoom($other);
        $active = BookingRoom::create([
            'id' => 'BR' . uniqid(), 'booking_id' => $booking->id, 'room_number' => '103',
            'room_class_id' => $this->roomClass->id, 'arrival_date' => '2026-11-06', 'departure_date' => '2026-11-08',
            'rate' => 500000, 'adults' => 1, 'status' => BookingRoom::STATUS_BOOKED,
        ]);

        foreach ([$foreign->id, $active->id] as $id) {
            $payload = $this->updatePayload($booking, []);
            $payload['noshow_rooms'] = [['bookingRoomId' => $id, 'arrivalDate' => '2026-11-07', 'departureDate' => '2026-11-08']];
            $this->putJson("/api/bookings/{$booking->id}", $payload)->assertStatus(422);
        }
        $this->assertSame('2026-11-06', $foreign->fresh()->arrival_date->toDateString());
    }
}
