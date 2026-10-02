<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\BookingRoomLifecycleService;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RoomPlanBookingRoomDateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RoomForm $roomForm;
    private BookingRoomLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'room_plan_resize_user']);
        $role = Role::firstOrCreate(
            ['code' => 'room_plan_resize_test'],
            ['name' => 'Room Plan resize test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true],
        );
        $permission = Permission::firstOrCreate(
            ['code' => 'fo.booking.edit'],
            ['name' => 'Edit booking', 'module' => 'FO'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->user->roles()->syncWithoutDetaching([$role->id]);
        $this->actingAs($this->user);

        foreach ([0, 1, 2, 3, 4, 100] as $status) {
            DB::table('booking_statuses')->updateOrInsert(
                ['id' => $status],
                ['name' => 'Status ' . $status],
            );
        }

        SystemDateRoll::create([
            'system_date' => '2026-09-01',
            'actual_date' => '2026-09-01',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        $this->roomForm = RoomForm::create(['name' => 'Standard']);
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);

        $this->lifecycle = Mockery::mock(BookingRoomLifecycleService::class);
        $this->lifecycle->shouldReceive('synchronize')->zeroOrMoreTimes();
        $this->app->instance(BookingRoomLifecycleService::class, $this->lifecycle);
        $this->useAvailability([1]);
    }

    public function test_room_plan_resize_expands_booking_header_without_changing_other_rooms(): void
    {
        $booking = $this->makeBooking();
        $target = $this->makeRoom($booking, '2026-09-08', '2026-09-10');
        $other = $this->makeRoom($booking, '2026-09-09', '2026-09-10');
        $this->setConfig('RoomPlan_AllowChangeArrivalDate', '1');

        $this->putJson($this->roomPlanUrl($booking, $target), [
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-12',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('booking_dates.departure_date', '2026-09-12');

        $this->assertSame('2026-09-12', $target->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-10', $other->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-08', $booking->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-09-12', $booking->fresh()->departure_date->toDateString());
        $this->assertSame(4, $booking->fresh()->num_of_days);
    }

    public function test_room_plan_arrival_change_requires_its_setting_and_expands_parent_when_enabled(): void
    {
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10');

        $this->setConfig('RoomPlan_AllowChangeArrivalDate', '0');
        $this->putJson($this->roomPlanUrl($booking, $room), [
            'arrival_date' => '2026-09-07',
            'departure_date' => '2026-09-10',
        ])->assertUnprocessable();
        $this->assertSame('2026-09-08', $booking->fresh()->arrival_date->toDateString());

        $this->setConfig('RoomPlan_AllowChangeArrivalDate', '1');
        $this->putJson($this->roomPlanUrl($booking, $room), [
            'arrival_date' => '2026-09-07',
            'departure_date' => '2026-09-10',
        ])->assertOk()
            ->assertJsonPath('booking_dates.arrival_date', '2026-09-07');

        $this->assertSame('2026-09-07', $booking->fresh()->arrival_date->toDateString());
        $this->assertSame(3, $booking->fresh()->num_of_days);
    }

    public function test_room_plan_resize_recalculates_parent_range_from_all_active_rooms(): void
    {
        $booking = $this->makeBooking();
        $booking->update(['departure_date' => '2026-09-12', 'num_of_days' => 4]);
        $target = $this->makeRoom($booking, '2026-09-08', '2026-09-12');
        $other = $this->makeRoom($booking, '2026-09-08', '2026-09-11');
        $this->setConfig('RoomPlan_AllowChangeArrivalDate', '1');

        $this->putJson($this->roomPlanUrl($booking, $target), [
            'arrival_date' => '2026-09-09',
            'departure_date' => '2026-09-10',
        ])->assertOk()
            ->assertJsonPath('booking_dates.arrival_date', '2026-09-08')
            ->assertJsonPath('booking_dates.departure_date', '2026-09-11');

        $this->assertSame('2026-09-09', $target->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-09-10', $target->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-08', $other->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-09-11', $other->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-08', $booking->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-09-11', $booking->fresh()->departure_date->toDateString());
        $this->assertSame(3, $booking->fresh()->num_of_days);
    }

    public function test_overbooking_setting_requires_confirmation_before_persisting_extended_dates(): void
    {
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10');
        $this->setConfig('AllowOverRoomTypeRoomKind', '1');
        $this->useAvailability([-1, -1]);
        $payload = ['arrival_date' => '2026-09-08', 'departure_date' => '2026-09-12'];

        $this->putJson($this->roomPlanUrl($booking, $room), $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'overbooking_confirmation_required');
        $this->assertSame('2026-09-10', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-10', $booking->fresh()->departure_date->toDateString());

        $this->putJson($this->roomPlanUrl($booking, $room), [...$payload, 'confirm_overbooking' => true])
            ->assertOk()
            ->assertJsonPath('warning', 'Cảnh báo: Số phòng trống của loại phòng đã bị âm (AV = -1).');

        $this->assertSame('2026-09-12', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-12', $booking->fresh()->departure_date->toDateString());
    }

    public function test_overbooking_is_blocked_when_setting_is_disabled(): void
    {
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10');
        $this->setConfig('AllowOverRoomTypeRoomKind', '0');
        $this->useAvailability([-1]);

        $this->putJson($this->roomPlanUrl($booking, $room), [
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-12',
            'confirm_overbooking' => true,
        ])->assertUnprocessable();

        $this->assertSame('2026-09-10', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-10', $booking->fresh()->departure_date->toDateString());
    }

    public function test_physical_room_conflict_blocks_extension_even_when_overbooking_is_allowed(): void
    {
        $booking = $this->makeBooking();
        Room::create([
            'room_number' => '101',
            'room_form_id' => $this->roomForm->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '1',
        ]);
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10', '101');
        $this->setConfig('AllowOverRoomTypeRoomKind', '1');
        $this->useAvailability([2], true);

        $this->putJson($this->roomPlanUrl($booking, $room), [
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-12',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Số phòng 101 đã được gán cho booking khác trong cùng khoảng thời gian.');

        $this->assertSame('2026-09-10', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-10', $booking->fresh()->departure_date->toDateString());
    }

    public function test_generic_booking_room_update_keeps_its_existing_parent_date_guard(): void
    {
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10');

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", [
            'arrival_date' => '2026-09-07',
            'departure_date' => '2026-09-10',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Thời gian ở của phòng phải nằm trong khoảng thời gian của booking (từ 2026-09-08 đến 2026-09-10).');

        $this->assertSame('2026-09-08', $room->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-09-08', $booking->fresh()->arrival_date->toDateString());
    }

    public function test_lifecycle_failure_rolls_back_room_and_parent_dates_together(): void
    {
        $booking = $this->makeBooking();
        $room = $this->makeRoom($booking, '2026-09-08', '2026-09-10');
        $lifecycle = Mockery::mock(BookingRoomLifecycleService::class);
        $lifecycle->shouldReceive('synchronize')->once()->andThrow(new \RuntimeException('lifecycle failed'));
        $this->app->instance(BookingRoomLifecycleService::class, $lifecycle);

        $this->putJson($this->roomPlanUrl($booking, $room), [
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-12',
        ])->assertInternalServerError();

        $this->assertSame('2026-09-10', $room->fresh()->departure_date->toDateString());
        $this->assertSame('2026-09-10', $booking->fresh()->departure_date->toDateString());
    }

    private function useAvailability(array $values, bool $roomOccupied = false): void
    {
        $availability = Mockery::mock(RoomAvailabilityService::class);
        $availability->shouldReceive('getSystemDate')->andReturn(Carbon::parse('2026-09-01'));
        $availability->shouldReceive('getAvailability')->andReturn(...$values);
        $availability->shouldReceive('isRoomNumberOccupied')->andReturn($roomOccupied);
        $this->app->instance(RoomAvailabilityService::class, $availability);
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'booking_name' => 'Room Plan resize',
            'booking_date' => '2026-09-01',
            'arrival_date' => '2026-09-08',
            'departure_date' => '2026-09-10',
            'num_of_days' => 2,
            'status' => Booking::STATUS_RESERVATION,
            'created_by' => $this->user->username,
        ]);
    }

    private function makeRoom(Booking $booking, string $arrival, string $departure, ?string $roomNumber = null): BookingRoom
    {
        return BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    private function setConfig(string $name, string $value): void
    {
        HotelConfig::updateOrCreate(['name' => $name], ['value' => $value]);
    }

    private function roomPlanUrl(Booking $booking, BookingRoom $room): string
    {
        return "/api/bookings/{$booking->id}/rooms/{$room->id}/room-plan-stay";
    }
}
