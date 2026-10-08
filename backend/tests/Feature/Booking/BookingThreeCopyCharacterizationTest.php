<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section S09 — Copy Booking per MÔ TẢ NGHIỆP VỤ !E25 specifications.
 */
class BookingThreeCopyCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private Booking $source;
    private BookingRoom $sourceRoom;
    private Guest $guest;
    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $user = User::factory()->create();
        $role = Role::create(['code' => 'copy_characterization', 'name' => 'Copy characterization', 'level' => 3]);
        $permission = Permission::firstOrCreate(['code' => 'fo.booking.create'], ['name' => 'Create booking', 'module' => 'FO']);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $this->actingAs($user);
        SystemDateRoll::create(['system_date' => '2026-08-16', 'actual_date' => '2026-08-16', 'shift' => '1', 'username' => $user->username]);
        $this->roomClass = RoomClass::create(['code' => 'COPY', 'name' => 'Copy test', 'is_active' => true]);
        $form = RoomForm::create(['name' => 'Copy standard']);
        Room::create(['room_number' => '101', 'room_class_id' => $this->roomClass->id, 'room_form_id' => $form->id, 'floor' => 1, 'status' => 'available']);
        $this->source = Booking::create(['booking_name' => 'Source copy', 'arrival_date' => '2026-08-16', 'departure_date' => '2026-08-18', 'num_of_days' => 2, 'booking_date' => '2026-08-16', 'note' => 'Source note', 'created_by' => $user->username]);
        $this->sourceRoom = BookingRoom::create(['booking_id' => $this->source->id, 'room_class_id' => $this->roomClass->id, 'room_number' => '101', 'arrival_date' => '2026-08-16', 'departure_date' => '2026-08-18', 'rate' => 1000000, 'adults' => 1, 'status' => BookingRoom::STATUS_BOOKED]);
        $this->guest = Guest::create(['full_name' => 'Copy guest']);
        BookingRoomGuest::create(['booking_room_id' => $this->sourceRoom->id, 'guest_id' => $this->guest->id, 'is_primary' => true, 'status' => BookingRoomGuest::STATUS_ACTIVE, 'actual_arrival_date' => '2026-08-16']);
        BookingRoomService::create(['booking_room_id' => $this->sourceRoom->id, 'service_code' => 'AUTO1', 'service_name' => 'Source setup', 'service_date' => '2026-08-16', 'quantity' => 1, 'rate' => 100000, 'total_amount' => 100000, 'is_posted' => 0]);
        ServiceBill::create(['OpenTime' => '00:00', 'Guest' => 'Copy guest', 'DepartmentId' => 'FO', 'DescriptionServive' => 'Source bill', 'Username' => $user->username, 'RegisterId1' => $this->source->id, 'RegisterID2' => $this->source->id, 'RentalRoomId1' => $this->sourceRoom->id, 'RentalRoomId2' => $this->sourceRoom->id, 'ServiceId' => 'MB', 'Date' => '2026-08-16', 'Amount' => 50000, 'Quantity' => 1, 'Status' => 1, 'Edit' => 0]);
        Payment::create(['booking_id' => $this->source->id, 'date' => '2026-08-16', 'amount' => 100000, 'pack2' => 'DPR', 'status' => 1, 'edit_flag' => 0]);
    }

    private function copyAndAssertLedgerUnchanged(array $extraParams = []): Booking
    {
        $ledger = [ServiceBill::orderBy('Ma')->get()->toArray(), Payment::orderBy('id')->get()->toArray(), BookingRoomService::orderBy('id')->get()->toArray()];
        $params = array_merge(['arrival_date' => '2026-08-26', 'departure_date' => '2026-08-28'], $extraParams);
        $response = $this->postJson("/api/bookings/{$this->source->id}/copy", $params)->assertStatus(201);
        $copy = Booking::findOrFail($response->json('data.id'));
        $this->assertNotSame((string) $this->source->id, (string) $copy->id);
        $this->assertSame('Source copy', $copy->booking_name);
        $this->assertSame('Source note', $copy->note);
        $this->assertSame('2026-08-26', $copy->arrival_date->toDateString());
        $this->assertSame($ledger, [ServiceBill::orderBy('Ma')->get()->toArray(), Payment::orderBy('id')->get()->toArray(), BookingRoomService::orderBy('id')->get()->toArray()]);
        $this->assertSame('2026-08-16', $this->sourceRoom->fresh()->arrival_date->toDateString());
        return $copy;
    }

    public function test_zero_copies_only_header_without_rooms_or_financial_rows(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '0']);
        $copy = $this->copyAndAssertLedgerUnchanged();
        $this->assertSame(0, $copy->bookingRooms()->count());
    }

    public function test_one_copies_room_and_guest_links_without_physical_assignment_or_setup(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        $copy = $this->copyAndAssertLedgerUnchanged();
        $room = $copy->bookingRooms()->sole();
        $this->assertNotSame((string) $this->sourceRoom->id, (string) $room->id);
        $this->assertNull($room->room_number);
        $this->assertEquals(1000000, $room->rate);
        $this->assertDatabaseHas('booking_room_guests', ['booking_room_id' => $room->id, 'guest_id' => $this->guest->id, 'is_primary' => true]);
        $pivot = BookingRoomGuest::where('booking_room_id', $room->id)->where('guest_id', $this->guest->id)->sole();
        $this->assertSame('2026-08-26', $pivot->actual_arrival_date->toDateString());
        $this->assertDatabaseHas('booking_room_guests', ['booking_room_id' => $this->sourceRoom->id, 'guest_id' => $this->guest->id]);
    }

    public function test_missing_key_copies_rooms_by_default(): void
    {
        HotelConfig::where('name', 'IsCopyAllBooking')->delete();
        $copy = $this->copyAndAssertLedgerUnchanged();
        $this->assertSame(1, $copy->bookingRooms()->count());
    }

    public function test_wb09_03_insufficient_av_allow_over_zero_prompts_no_rooms_available(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        $this->mock(RoomAvailabilityService::class)->shouldReceive('getAvailability')->once()->andReturn(0);

        $response = $this->postJson("/api/bookings/{$this->source->id}/copy", [
            'arrival_date' => '2026-08-26',
            'departure_date' => '2026-08-28',
        ])->assertStatus(422);

        $response->assertJsonPath('require_confirm', 'no_rooms_available');
        $response->assertJsonPath('message', 'Không còn phòng trống, bạn có muốn tiếp tục thao tác');
        $this->assertSame(1, Booking::count()); // No new booking created
    }

    public function test_wb09_03_insufficient_av_allow_over_zero_confirmed_copies_header_only(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);

        $copy = $this->copyAndAssertLedgerUnchanged(['copy_header_only' => true]);
        $this->assertSame(0, $copy->bookingRooms()->count());
    }

    public function test_wb09_05_insufficient_av_allow_over_one_prompts_over_warning(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $this->mock(RoomAvailabilityService::class)->shouldReceive('getAvailability')->once()->andReturn(0);

        $response = $this->postJson("/api/bookings/{$this->source->id}/copy", [
            'arrival_date' => '2026-08-26',
            'departure_date' => '2026-08-28',
        ])->assertStatus(422);

        $response->assertJsonPath('require_confirm', 'over_warning');
        $response->assertJsonPath('message', 'Phòng âm bạn có muốn tiếp tục thao tác');
        $this->assertSame(1, Booking::count()); // No new booking created
    }

    public function test_wb09_05_insufficient_av_allow_over_one_confirmed_copies_rooms_overbooked(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);
        $this->mock(RoomAvailabilityService::class)->shouldReceive('getAvailability')->once()->andReturn(0);

        $copy = $this->copyAndAssertLedgerUnchanged(['confirm_over' => true]);
        $this->assertSame(1, $copy->bookingRooms()->count());
    }

    public function test_wb09_07_audit_fields_use_pms_system_date(): void
    {
        HotelConfig::updateOrCreate(['name' => 'IsCopyAllBooking'], ['value' => '1']);
        $copy = $this->copyAndAssertLedgerUnchanged();

        $this->assertSame('2026-08-16', $copy->booking_date->toDateString());
        $this->assertSame('2026-08-16', $copy->confirm_date->toDateString());
        $room = $copy->bookingRooms()->sole();
        $this->assertNotNull($room);
    }
}
