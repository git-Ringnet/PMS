<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
use App\Models\BookingRoom;
use App\Models\BookingRoomChild;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\RoomClass;
use App\Models\RoomRateCode;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\BookingRoomStayChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestControllerFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->user = User::factory()->create(['username' => 'guest_fixes_test']);
        $role = Role::create([
            'code' => 'guest_fixes_test',
            'name' => 'Guest fixes test',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permission = Permission::firstOrCreate(
            ['code' => 'fo.booking.edit'],
            ['name' => 'Booking edit', 'module' => 'FO']
        );
        $role->permissions()->attach($permission->id);
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        SystemDateRoll::create([
            'system_date' => '2026-08-09 00:00:00',
            'actual_date' => '2026-08-09 00:00:00',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        // The booking column stores the SP1311 business code after cutover.
        RegistrationStatus::create([
            'id' => 1,
            'booking_status_id' => 1,
            'name' => 'Guaranteed',
            'is_availability' => true,
        ]);

        $this->roomClass = RoomClass::create([
            'code' => 'GST',
            'name' => 'Guest test room',
            'is_active' => true,
        ]);
    }

    public function test_guest_screen_whitelists_history_but_init_only_creates_current_guests(): void
    {
        $booking = $this->makeBooking();
        $rooms = [];
        foreach ([
            BookingRoom::STATUS_BOOKED,
            BookingRoom::STATUS_CHECKED_IN,
            BookingRoom::STATUS_CHECKED_OUT,
            BookingRoom::STATUS_CANCELLED,
            BookingRoom::STATUS_NOSHOW,
            BookingRoom::STATUS_MOVED,
        ] as $index => $status) {
            $rooms[$status] = $this->makeRoom($booking, "G-GUEST-{$index}", $status);
        }

        $movedChild = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $rooms[BookingRoom::STATUS_MOVED]->id,
            'full_name' => 'Moved child',
            'age_group' => 'child',
            'child_status' => 0,
        ]);
        BookingRoomChild::where('booking_child_id', $movedChild->id)
            ->where('booking_room_id', $rooms[BookingRoom::STATUS_MOVED]->id)
            ->update(['status' => BookingRoom::STATUS_MOVED]);
        BookingRoomChild::create([
            'booking_child_id' => $movedChild->id,
            'booking_room_id' => $rooms[BookingRoom::STATUS_BOOKED]->id,
            'status' => 1,
        ]);

        $response = $this->getJson("/api/bookings/{$booking->id}/guests")
            ->assertSuccessful();
        $roomIds = collect($response->json('data'))->pluck('booking_room_id')->all();
        $this->assertSame([
            $rooms[BookingRoom::STATUS_BOOKED]->id,
            $rooms[BookingRoom::STATUS_CHECKED_IN]->id,
            $rooms[BookingRoom::STATUS_CHECKED_OUT]->id,
            $rooms[BookingRoom::STATUS_NOSHOW]->id,
        ], $roomIds);

        $this->postJson("/api/bookings/{$booking->id}/init-guests")
            ->assertSuccessful();

        $this->assertSame(1, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_BOOKED]->id)->count());
        $this->assertSame(1, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_CHECKED_IN]->id)->count());
        $this->assertSame(0, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_CHECKED_OUT]->id)->count());
        $this->assertSame(0, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_NOSHOW]->id)->count());
        $this->assertSame(0, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_CANCELLED]->id)->count());
        $this->assertSame(0, BookingRoomGuest::where('booking_room_id', $rooms[BookingRoom::STATUS_MOVED]->id)->count());

        $this->getJson("/api/bookings/{$booking->id}/children?booking_room_id={$rooms[BookingRoom::STATUS_BOOKED]->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $movedChild->id);
    }

    public function test_guest_date_edit_removes_only_stale_unposted_rm_and_keeps_manual_and_posted_money(): void
    {
        $booking = $this->makeBooking();
        RoomRateCode::create(['Ma' => 'BAR', 'Description' => 'Bar test rate']);
        $room = $this->makeRoom($booking, 'G-GUEST-STAY', BookingRoom::STATUS_BOOKED, [
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-12',
            'rate' => 500000,
            'rate_code' => 'BAR',
        ]);
        $guest = Guest::create(['full_name' => 'Original guest']);
        BookingRoomGuest::create([
            'booking_room_id' => $room->id,
            'guest_id' => $guest->id,
            'is_primary' => true,
            'status' => BookingRoomGuest::STATUS_ACTIVE,
        ]);

        $posted = $this->makeRoomService($room, '2026-08-09', 111000, true);
        $surviving = $this->makeRoomService($room, '2026-08-10', 222000, false);
        $stale = $this->makeRoomService($room, '2026-08-11', 333000, false);
        $outside = $this->makeRoomService($room, '2026-08-12', 444000, false);
        $manual = BookingRoomService::create([
            'booking_room_id' => $room->id,
            'service_code' => 'SPA',
            'service_name' => 'Manual service',
            'service_date' => '2026-08-12',
            'quantity' => 1,
            'rate' => 99000,
            'is_posted' => 0,
        ]);

        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'Updated guest',
            'departure_date' => '2026-08-11',
            // The detail screen sends these unchanged values on a date-only edit.
            'rate' => '500000.00',
            'rate_code' => 'BAR',
        ])->assertSuccessful();

        $this->assertSame('2026-08-11', $room->fresh()->departure_date->toDateString());
        $this->assertSame(222000.0, (float) $surviving->fresh()->rate);
        $this->assertSame(111000.0, (float) $posted->fresh()->rate);
        $this->assertNotNull(BookingRoomService::withTrashed()->find($stale->id)->deleted_at);
        $this->assertNotNull(BookingRoomService::withTrashed()->find($outside->id)->deleted_at);
        $this->assertSame(99000.0, (float) $manual->fresh()->rate);

        $historicalRoom = $this->makeRoom($booking, 'G-GUEST-HISTORY', BookingRoom::STATUS_CHECKED_OUT, [
            'departure_date' => '2026-08-11',
        ]);
        $historicalCharge = $this->makeRoomService($historicalRoom, '2026-08-12', 555000, false);
        app(BookingRoomStayChargeService::class)->synchronize($historicalRoom->fresh());
        $this->assertNull($historicalCharge->fresh()->deleted_at);
    }

    public function test_breakfast_amount_updates_projected_service_but_never_rewrites_posted_service(): void
    {
        HotelConfig::updateOrCreate(
            ['name' => 'Booking_BFChildSetServiceId'],
            ['value' => 'BD']
        );
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, 'G-GUEST-BF', BookingRoom::STATUS_CHECKED_IN, [
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-11',
        ]);
        $child = BookingChild::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Breakfast child',
            'age_group' => 'child',
            'child_status' => 1,
        ]);
        $detail = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-09',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'is_room' => true,
            'amount' => 90000,
        ]);
        $postedDetail = BookingChildBreakfastDetail::create([
            'booking_child_id' => $child->id,
            'service_date' => '2026-08-10',
            'breakfast' => true,
            'is_free' => false,
            'is_extra_charge' => true,
            'is_room' => true,
            'amount' => 90000,
        ]);
        $note = 'Phụ thu ăn sáng trẻ em: Breakfast child';
        $projected = $this->makeBreakfastService($room, '2026-08-09', $note, 90000, false);
        $posted = $this->makeBreakfastService($room, '2026-08-10', $note, 90000, true);

        $this->getJson("/api/bookings/{$booking->id}/children")
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $child->id);

        $this->patchJson("/api/booking-children/{$child->id}/breakfast-details/{$detail->id}", [
            'amount' => 123456,
        ])->assertSuccessful();
        $this->assertSame(123456.0, (float) $detail->fresh()->amount);
        $this->assertSame(123456.0, (float) $projected->fresh()->rate);

        $this->patchJson("/api/booking-children/{$child->id}/breakfast-details/{$postedDetail->id}", [
            'amount' => 654321,
        ])->assertSuccessful();
        $this->assertSame(654321.0, (float) $postedDetail->fresh()->amount);
        $this->assertSame(90000.0, (float) $posted->fresh()->rate);
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Guest fixes booking',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-12',
            'num_of_days' => 3,
            'booking_date' => '2026-08-09',
            'created_by' => $this->user->username,
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => 1,
        ], $attributes));
    }

    private function makeRoom(Booking $booking, string $id, int $status, array $attributes = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'id' => $id,
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-12',
            'adults' => 1,
            'status' => $status,
            'rate' => 500000,
        ], $attributes));
    }

    private function makeRoomService(BookingRoom $room, string $date, int $rate, bool $posted): BookingRoomService
    {
        return BookingRoomService::create([
            'booking_room_id' => $room->id,
            'service_code' => 'RM',
            'service_name' => 'Room charge',
            'service_date' => $date,
            'quantity' => 1,
            'rate' => $rate,
            'is_room' => 1,
            'is_posted' => $posted ? 1 : 0,
        ]);
    }

    private function makeBreakfastService(BookingRoom $room, string $date, string $note, int $rate, bool $posted): BookingRoomService
    {
        return BookingRoomService::create([
            'booking_room_id' => $room->id,
            'service_code' => 'BD',
            'service_name' => $note,
            'service_date' => $date,
            'quantity' => 1,
            'rate' => $rate,
            'note' => $note,
            'is_room' => 1,
            'is_posted' => $posted ? 1 : 0,
        ]);
    }
}
