<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingCancelLog;
use App\Models\BookingChild;
use App\Models\BookingChildBreakfastDetail;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\BookingRoomService;
use App\Models\Department;
use App\Models\Guest;
use App\Models\HotelConfig;
use App\Models\HotelService;
use App\Models\HotelSetting;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Booking 4 document (14/10/2026): sections 1-11.
 */
class BookingFourSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected RoomClass $roomClass;
    protected RegistrationStatus $regStatus;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'test_user']);
        $role = Role::create(['code' => 'booking_four_test', 'name' => 'Booking four test', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach ([
            'fo.booking.create', 'fo.booking.edit', 'fo.booking.cancel', 'fo.frontdesk.view', 'fo.checkin',
            'fo.checkout', 'fo.room.move', 'fo.service.view', 'fo.service.add', 'fo.service.edit', 'fo.service.delete',
        ] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
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
            ['id' => 100, 'name' => 'Chuyển phòng'],
        ]);

        SystemDateRoll::create([
            'system_date' => '2026-08-07 00:00:00',
            'actual_date' => '2026-08-07 00:00:00',
            'shift' => '1',
            'username' => 'test_user',
        ]);

        $roomForm = RoomForm::create(['name' => 'Double']);
        $this->roomClass = RoomClass::create(['code' => 'SUPD', 'name' => 'Superior Double', 'is_active' => true]);
        foreach (['101', '102', '103'] as $number) {
            Room::create([
                'room_number' => $number,
                'room_class_id' => $this->roomClass->id,
                'room_form_id' => $roomForm->id,
                'floor' => 1,
                'status' => 'available',
            ]);
        }

        $this->regStatus = RegistrationStatus::create(['name' => 'Guaranteed', 'booking_status_id' => 1, 'is_availability' => true]);
        DB::table('markets')->insertOrIgnore([['id' => 1, 'name' => 'FIT', 'code' => 'FIT', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('customer_sources')->insertOrIgnore([['id' => 1, 'name' => 'WalkIn', 'code' => 'WI', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('companies')->insertOrIgnore([['id' => 1, 'name' => 'WalkIn Company', 'code' => 'WIC', 'created_at' => now(), 'updated_at' => now()]]);

        HotelConfig::create(['name' => 'AllowOverRoomTypeRoomKind', 'value' => '0']);
        HotelConfig::create(['name' => 'Booking_BFChildSetServiceId', 'value' => 'BD']);
        HotelSetting::create(['hotel_name' => 'Test Hotel', 'breakfast_child_rate' => 90000]);

        $this->department = Department::firstOrCreate(['code' => 'FO'], ['name' => 'Reception/ Lê Tân']);
        foreach ([
            ['code' => 'RM', 'name' => 'Dịch vụ phòng nghỉ'],
            ['code' => 'EB', 'name' => 'Extrabed/Thêm Giường'],
            ['code' => 'BD', 'name' => 'Breakfast Child/Ăn Sáng Trẻ Em'],
            ['code' => 'KC', 'name' => 'Kid Surcharge/ Phụ thu trẻ em'],
            ['code' => 'TR', 'name' => 'Transfer/Đưa đón'],
        ] as $service) {
            HotelService::create($service + ['is_active' => true, 'price' => 0, 'department_id' => $this->department->id]);
        }
    }

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Booking Four',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-10',
            'num_of_days' => 3,
            'booking_date' => '2026-08-07',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'created_by' => 'test_user',
            'status' => Booking::STATUS_RESERVATION,
        ], $attributes));
    }

    private function createRoom(Booking $booking, string $id, array $attributes = []): BookingRoom
    {
        return BookingRoom::create(array_merge([
            'id' => $id,
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-10',
            'rate' => 500000,
            'status' => BookingRoom::STATUS_BOOKED,
        ], $attributes));
    }

    private function createChildWithSurcharge(Booking $booking, BookingRoom $room, array $dates, float $amount = 90000, bool $isRoom = true): BookingChild
    {
        $child = BookingChild::create([
            'id' => 'T' . str_pad((string) (BookingChild::count() + 1), 9, '0', STR_PAD_LEFT),
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'full_name' => 'Child 1',
            'age_group' => 'child',
            'child_status' => 0,
        ]);
        foreach ($dates as $date) {
            BookingChildBreakfastDetail::create([
                'booking_child_id' => $child->id,
                'service_date' => $date,
                'breakfast' => true,
                'is_free' => false,
                'is_extra_charge' => true,
                'is_room' => $isRoom,
                'amount' => $amount,
            ]);
        }

        return $child;
    }

    private function detailDates(BookingChild $child): array
    {
        return $child->breakfastDetails()->orderBy('service_date')->get()
            ->map(fn ($detail) => $detail->service_date->toDateString())->all();
    }

    // ---------------------------------------------------------------- S1
    public function test_s1_extra_bed_day_set_to_zero_keeps_the_row_with_zero_quantity(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-EB', ['extra_bed_qty' => 1, 'extra_bed_rate' => 300000]);
        foreach (['2026-08-07', '2026-08-08', '2026-08-09'] as $date) {
            BookingRoomService::create([
                'booking_room_id' => $room->id, 'service_code' => 'EB', 'service_name' => 'Extra Bed',
                'service_date' => $date, 'quantity' => 1, 'rate' => 300000, 'is_posted' => 0,
            ]);
        }

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => 'EB', 'service_date' => '2026-08-07', 'quantity' => 0, 'rate' => 300000,
        ])->assertSuccessful();

        $row = BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'EB')->whereDate('service_date', '2026-08-07')->first();
        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row->quantity);
        $this->assertSame(0.0, (float) $row->total_amount);
        $this->assertSame(2, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'EB')->where('quantity', '>', 0)->count());
    }

    // ---------------------------------------------------------------- S2
    public function test_s2_quick_update_departure_updates_booking_night_count(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-12', 'num_of_days' => 5]);
        $room = $this->createRoom($booking, 'G4-QU', ['departure_date' => '2026-08-12']);

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [
            'room_ids' => [$room->id],
            'departure_date' => '2026-08-13',
        ])->assertSuccessful();

        $booking->refresh();
        $this->assertSame('2026-08-13', $booking->departure_date->toDateString());
        $this->assertSame(6, (int) $booking->num_of_days);
    }

    // ---------------------------------------------------------------- S3
    public function test_s3_detail_mode_service_screen_updates_only_breakfast_details(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-BFD');
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08', '2026-08-09'], 0, true);
        BookingChildBreakfastDetail::where('booking_child_id', $child->id)->update(['is_extra_charge' => false]);

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => 'BD', 'service_date' => '2026-08-08', 'quantity' => 1, 'rate' => 120000,
        ])->assertSuccessful()->assertJsonPath('child_breakfast', true);

        $this->assertSame(0, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'BD')->count());
        $detail = $child->breakfastDetails()->whereDate('service_date', '2026-08-08')->first();
        $this->assertTrue((bool) $detail->is_extra_charge);
        $this->assertSame(120000.0, (float) $detail->amount);
        $this->assertFalse((bool) $child->breakfastDetails()->whereDate('service_date', '2026-08-07')->first()->is_extra_charge);
    }

    public function test_s3_service_mode_keeps_inserting_booking_room_services(): void
    {
        HotelConfig::where('name', 'Booking_BFChildSetServiceId')->update(['value' => 'KC']);
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-BFS');
        $this->createChildWithSurcharge($booking, $room, ['2026-08-07']);

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => 'KC', 'service_date' => '2026-08-08', 'quantity' => 1, 'rate' => 90000,
        ])->assertSuccessful();

        $this->assertDatabaseHas('booking_room_services', ['booking_room_id' => $room->id, 'service_code' => 'KC', 'rate' => 90000]);
        $this->getJson('/api/bookings/init-dropdowns')
            ->assertJsonPath('data.child_breakfast.detail_mode', false)
            ->assertJsonPath('data.child_breakfast.service_code', 'KC');
    }

    public function test_s3_booking_edit_moving_stay_keeps_child_breakfast_price_and_surcharge(): void
    {
        HotelConfig::create(['name' => 'SyncRoomDateByBookingDate', 'value' => '1']);
        $booking = $this->createBooking(['arrival_date' => '2026-08-07', 'departure_date' => '2026-08-15', 'num_of_days' => 8]);
        $room = $this->createRoom($booking, 'G4-BFM', ['arrival_date' => '2026-08-09', 'departure_date' => '2026-08-13', 'children_qty' => 1]);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-09', '2026-08-10', '2026-08-11', '2026-08-12'], 90000, false);

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-15',
            'num_of_days' => 8,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'rooms' => [[
                    'bookingRoomId' => $room->id,
                    'arrivalDate' => '2026-08-10',
                    'departureDate' => '2026-08-14',
                    'price' => 500000,
                    'children' => 1,
                ]],
            ]],
        ])->assertSuccessful();

        $this->assertSame('2026-08-10', $room->fresh()->arrival_date->toDateString());
        $this->assertSame(['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13'], $this->detailDates($child));
        foreach ($child->breakfastDetails as $detail) {
            $this->assertTrue((bool) $detail->is_extra_charge);
            $this->assertFalse((bool) $detail->is_room);
            $this->assertSame(90000.0, (float) $detail->amount);
        }
    }

    public function test_s3_room_map_date_edit_moves_child_breakfast_dates(): void
    {
        $booking = $this->createBooking(['departure_date' => '2026-08-12']);
        $room = $this->createRoom($booking, 'G4-BFR', ['departure_date' => '2026-08-10']);
        $guest = Guest::create(['full_name' => 'Room Map Guest', 'guest_status' => Guest::STATUS_ACTIVE]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => 1, 'status' => 0]);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08', '2026-08-09']);

        $this->putJson("/api/booking-rooms/{$room->id}/guests/{$guest->id}", [
            'full_name' => 'Room Map Guest',
            'departure_date' => '2026-08-09',
        ])->assertSuccessful();

        $this->assertSame(['2026-08-07', '2026-08-08'], $this->detailDates($child));
        $this->assertSame(90000.0, (float) $child->breakfastDetails()->first()->amount);
    }

    public function test_s3_saving_guest_info_keeps_surcharge_setup_when_dates_are_rebuilt(): void
    {
        // Image 3: details 09-12 with surcharge, room moved to 14-18, saving the
        // guest info used to create 14-17 with surcharge off and amount 0.
        HotelConfig::updateOrCreate(['name' => 'Booking_AutoExtraChargeBFChild'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'BreakfastRateChild'], ['value' => '0']);
        $booking = $this->createBooking(['departure_date' => '2026-08-20']);
        $room = $this->createRoom($booking, 'G4-BFG', ['arrival_date' => '2026-08-09', 'departure_date' => '2026-08-13']);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-09', '2026-08-10', '2026-08-11', '2026-08-12'], 90000, true);
        BookingRoom::whereKey($room->id)->update(['arrival_date' => '2026-08-14', 'departure_date' => '2026-08-18']);

        $this->postJson("/api/bookings/{$booking->id}/bulk-update-guests", [
            'guests' => [],
            'children' => [['id' => $child->id, 'full_name' => 'Child 1']],
        ])->assertSuccessful();

        $this->assertSame(['2026-08-14', '2026-08-15', '2026-08-16', '2026-08-17'], $this->detailDates($child));
        foreach ($child->breakfastDetails as $detail) {
            $this->assertTrue((bool) $detail->is_extra_charge);
            $this->assertSame(90000.0, (float) $detail->amount);
        }
    }

    public function test_s3_service_screen_quantity_zero_turns_the_surcharge_off_for_that_date(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-BFZ');
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08']);

        $this->postJson("/api/booking-rooms/{$room->id}/services", [
            'service_code' => 'BD', 'service_date' => '2026-08-08', 'quantity' => 0, 'rate' => 0,
        ])->assertSuccessful();

        $this->assertFalse((bool) $child->breakfastDetails()->whereDate('service_date', '2026-08-08')->first()->is_extra_charge);
        $this->assertTrue((bool) $child->breakfastDetails()->whereDate('service_date', '2026-08-07')->first()->is_extra_charge);
        $this->assertSame(0, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'BD')->count());
    }

    public function test_s3_legacy_bd_rows_are_not_posted_in_detail_mode(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_CHECKIN]);
        $room = $this->createRoom($booking, 'G4-LEG', ['room_number' => '101', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07'], 90000, true);
        // Old implementation left a projected BD row with a stale price.
        BookingRoomService::create([
            'booking_room_id' => $room->id, 'service_code' => 'BD', 'service_name' => 'Phụ thu ăn sáng trẻ em: Child 1',
            'note' => 'Phụ thu ăn sáng trẻ em: Child 1', 'service_date' => '2026-08-07', 'quantity' => 1, 'rate' => 50000, 'is_posted' => 0,
        ]);

        $this->postJson('/api/booking-room-services/post-room-charge', [
            'booking_room_id' => $room->id, 'date_from' => '2026-08-07', 'date_to' => '2026-08-07', 'mode' => 'auto',
        ])->assertSuccessful();

        $bills = ServiceBill::where('RentalRoomId1', $room->id)->where('ServiceId', 'BD')->get();
        $this->assertCount(1, $bills);
        $this->assertSame(90000.0, (float) $bills->first()->Amount);
        $this->assertSame((string) $child->id, (string) $bills->first()->CustomerId1);
    }

    public function test_s3_children_set_to_zero_deletes_breakfast_details(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-BF0', ['children_qty' => 1]);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08']);

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", ['children' => 0])->assertSuccessful();

        $this->assertSame(0, BookingChildBreakfastDetail::where('booking_child_id', $child->id)->count());
        $this->assertSame(0, (int) $room->fresh()->children_qty);
    }

    // ---------------------------------------------------------------- Dòng 417

    private function breakfastFlags(BookingRoom $room): array
    {
        return BookingChildBreakfastDetail::whereIn('booking_child_id', BookingChild::where('booking_room_id', $room->id)->pluck('id'))
            ->get()
            ->map(fn (BookingChildBreakfastDetail $detail) => [(bool) $detail->breakfast, (float) $detail->amount])
            ->unique()
            ->values()
            ->all();
    }

    public function test_row_417_new_room_without_breakfast_adds_children_without_breakfast(): void
    {
        HotelConfig::create(['name' => 'BreakfastRateChild', 'value' => '70000']);
        $booking = $this->createBooking();
        $existing = $this->createRoom($booking, 'G4-417A', ['breakfast' => true]);

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-10',
            'num_of_days' => 3,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'rooms' => [
                    ['bookingRoomId' => $existing->id, 'price' => 500000, 'breakfast' => true],
                    ['price' => 500000, 'breakfast' => false, 'children' => 1, 'babies' => 1],
                ],
            ]],
        ])->assertSuccessful();

        $newRoom = BookingRoom::where('booking_id', $booking->id)->where('id', '!=', $existing->id)->firstOrFail();
        $this->assertFalse((bool) $newRoom->breakfast);
        $this->assertSame(2, BookingChild::where('booking_room_id', $newRoom->id)->count());
        $this->assertSame([[false, 0.0]], $this->breakfastFlags($newRoom));
    }

    public function test_row_417_existing_room_without_breakfast_adds_children_without_breakfast(): void
    {
        HotelConfig::create(['name' => 'BreakfastRateChild', 'value' => '70000']);
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-417B', ['breakfast' => false]);

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", ['children' => 1, 'babies' => 1])->assertSuccessful();

        $this->assertSame(2, BookingChild::where('booking_room_id', $room->id)->count());
        $this->assertSame([[false, 0.0]], $this->breakfastFlags($room));
    }

    public function test_row_417_room_with_breakfast_still_adds_children_with_breakfast(): void
    {
        HotelConfig::create(['name' => 'BreakfastRateChild', 'value' => '70000']);
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-417C', ['breakfast' => true]);

        $this->putJson("/api/bookings/{$booking->id}/rooms/{$room->id}", ['children' => 1])->assertSuccessful();

        $this->assertSame([[true, 70000.0]], $this->breakfastFlags($room));
    }

    public function test_row_417_room_map_add_child_follows_room_breakfast(): void
    {
        HotelConfig::create(['name' => 'BreakfastRateChild', 'value' => '70000']);
        $booking = $this->createBooking();
        $noBreakfast = $this->createRoom($booking, 'G4-417D', ['breakfast' => false]);
        $withBreakfast = $this->createRoom($booking, 'G4-417E', ['breakfast' => true]);

        $this->postJson("/api/bookings/{$booking->id}/children", ['booking_room_id' => $noBreakfast->id, 'full_name' => 'Child A', 'age_group' => 'child'])->assertSuccessful();
        $this->postJson("/api/bookings/{$booking->id}/children", ['booking_room_id' => $noBreakfast->id, 'full_name' => 'Baby A', 'age_group' => 'baby'])->assertSuccessful();
        $this->postJson("/api/bookings/{$booking->id}/children", ['booking_room_id' => $withBreakfast->id, 'full_name' => 'Child B', 'age_group' => 'child'])->assertSuccessful();

        $this->assertSame([[false, 0.0]], $this->breakfastFlags($noBreakfast));
        $this->assertSame([[true, 70000.0]], $this->breakfastFlags($withBreakfast));
    }

    public function test_row_417_room_info_turning_breakfast_off_and_adding_child_in_one_save(): void
    {
        HotelConfig::create(['name' => 'BreakfastRateChild', 'value' => '70000']);
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-417F', ['breakfast' => true]);

        $this->postJson("/api/bookings/{$booking->id}/children", [
            'booking_room_id' => $room->id,
            'full_name' => 'Child F',
            'age_group' => 'child',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-10',
            'breakfast' => false,
        ])->assertSuccessful();

        $this->assertFalse((bool) $room->fresh()->breakfast);
        $this->assertSame([[false, 0.0]], $this->breakfastFlags($room));
    }

    // ---------------------------------------------------------------- S5
    public function test_s5_shortening_stay_keeps_additional_services_but_removes_projected_room_charges(): void
    {
        HotelConfig::create(['name' => 'SyncRoomDateByBookingDate', 'value' => '1']);
        $booking = $this->createBooking(['departure_date' => '2026-08-11', 'num_of_days' => 4]);
        $room = $this->createRoom($booking, 'G4-SV', ['departure_date' => '2026-08-11']);
        foreach (['2026-08-07', '2026-08-08', '2026-08-09', '2026-08-10'] as $date) {
            BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'TR', 'service_name' => 'Đưa đón', 'service_date' => $date, 'quantity' => 1, 'rate' => 100000, 'is_posted' => 0]);
            BookingRoomService::create(['booking_room_id' => $room->id, 'service_code' => 'RM', 'service_name' => 'Tiền phòng', 'service_date' => $date, 'quantity' => 1, 'rate' => 500000, 'is_posted' => 0]);
        }

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-11',
            'num_of_days' => 4,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'rooms' => [['bookingRoomId' => $room->id, 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-09', 'price' => 500000]],
            ]],
        ])->assertSuccessful();

        $this->assertSame(4, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'TR')->count());
        $this->assertSame(2, BookingRoomService::where('booking_room_id', $room->id)->where('service_code', 'RM')->count());
    }

    // ---------------------------------------------------------------- S7
    public function test_s7_cancelling_a_room_twice_is_idempotent_and_unknown_room_gets_a_clear_message(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-CX');
        $this->createRoom($booking, 'G4-CX2');

        $this->deleteJson("/api/bookings/{$booking->id}/rooms/{$room->id}/cancel", ['note' => 'Khách đổi kế hoạch'])
            ->assertSuccessful();
        $this->deleteJson("/api/bookings/{$booking->id}/rooms/{$room->id}/cancel", ['note' => 'Khách đổi kế hoạch'])
            ->assertSuccessful()->assertJsonPath('already_cancelled', true);

        $this->assertSame(1, BookingCancelLog::where('booking_room_id', $room->id)->count());
        $this->deleteJson("/api/bookings/{$booking->id}/rooms/G9999999/cancel")
            ->assertStatus(404)->assertJsonPath('code', 'room_not_in_booking');
    }

    // ---------------------------------------------------------------- S8
    public function test_s8_cancel_booking_covers_all_rooms_and_restore_matches_active_rooms(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10', 'num_of_days' => 1]);
        $sameDay = $this->createRoom($booking, 'G4-R1', ['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-08', 'is_day_use' => true, 'status' => BookingRoom::STATUS_CANCELLED]);
        $overnight = $this->createRoom($booking, 'G4-R2', ['arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10']);

        $this->deleteJson("/api/bookings/{$booking->id}", ['note' => 'Hủy'])->assertSuccessful();
        $booking->refresh();
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-10', $booking->departure_date->toDateString());

        $this->postJson("/api/bookings/{$booking->id}/restore")->assertSuccessful();
        $booking->refresh();
        $this->assertSame(Booking::STATUS_RESERVATION, (int) $booking->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $sameDay->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $overnight->fresh()->status);
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame('2026-08-10', $booking->departure_date->toDateString());
        $this->assertSame(2, (int) $booking->num_of_days);
    }

    public function test_s8_restore_skips_past_rooms_and_narrows_booking_period(): void
    {
        $booking = $this->createBooking(['arrival_date' => '2026-08-06', 'departure_date' => '2026-08-10', 'status' => Booking::STATUS_DELETED]);
        $past = $this->createRoom($booking, 'G4-P1', ['arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07', 'status' => BookingRoom::STATUS_CANCELLED]);
        $future = $this->createRoom($booking, 'G4-P2', ['arrival_date' => '2026-08-08', 'departure_date' => '2026-08-10', 'status' => BookingRoom::STATUS_CANCELLED]);

        $this->postJson("/api/bookings/{$booking->id}/restore")->assertSuccessful()->assertJsonPath('rooms_restored', true);

        $this->assertSame(BookingRoom::STATUS_CANCELLED, (int) $past->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $future->fresh()->status);
        $booking->refresh();
        $this->assertSame('2026-08-08', $booking->arrival_date->toDateString());
        $this->assertSame(2, (int) $booking->num_of_days);
    }

    // S8 end-to-end theo đúng kịch bản trong tài liệu
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'booking_name' => 'Walk-in',
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ], $overrides);
    }

    private function runFlow(string $systemDate, ?string $restoreSystemDate = null): array
    {
        SystemDateRoll::query()->delete();
        SystemDateRoll::create(['system_date' => $systemDate . ' 00:00:00', 'actual_date' => $systemDate . ' 00:00:00', 'shift' => '1', 'username' => 'test_user']);

        // 1. Booking 1 phòng 01/09 -> 01/09
        $create = $this->postJson('/api/bookings', $this->basePayload([
            'arrival_date' => '2026-09-01', 'departure_date' => '2026-09-01', 'num_of_days' => 0, 'is_day_use' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id, 'quantity' => 1,
                'rooms' => [['arrivalDate' => '2026-09-01', 'departureDate' => '2026-09-01', 'price' => 540000]],
            ]],
        ]))->assertStatus(201);
        $bookingId = $create->json('data.id');
        $first = BookingRoom::where('booking_id', $bookingId)->first();

        // 2. Hủy phòng đầu
        $this->deleteJson("/api/bookings/{$bookingId}/rooms/{$first->id}/cancel", ['note' => 'Hủy'])->assertSuccessful();

        // 3. Sửa giai đoạn BK 02/09 -> 03/09 rồi lấy phòng 02/09 -> 03/09
        $this->putJson("/api/bookings/{$bookingId}", $this->basePayload([
            'arrival_date' => '2026-09-02', 'departure_date' => '2026-09-03', 'num_of_days' => 1, 'is_day_use' => 0,
        ]))->assertSuccessful();
        $this->postJson("/api/bookings/{$bookingId}/add-rooms", [
            'intent' => 'add_only',
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id, 'quantity' => 1,
                'rooms' => [['arrivalDate' => '2026-09-02', 'departureDate' => '2026-09-03', 'price' => 500000]],
            ]],
        ])->assertSuccessful();
        $second = BookingRoom::where('booking_id', $bookingId)->where('id', '!=', $first->id)->first();

        $booking = Booking::find($bookingId);
        $state = ['before_delete' => [$booking->arrival_date->toDateString(), $booking->departure_date->toDateString()]];

        if ($restoreSystemDate) {
            SystemDateRoll::create(['system_date' => $restoreSystemDate . ' 00:00:00', 'actual_date' => $restoreSystemDate . ' 00:00:00', 'shift' => '1', 'username' => 'test_user']);
        }

        // 4. Xóa đăng ký
        $this->deleteJson("/api/bookings/{$bookingId}", ['note' => 'Hủy BK'])->assertSuccessful();
        $booking->refresh();
        $state['after_delete'] = [$booking->arrival_date->toDateString(), $booking->departure_date->toDateString(), (int) $booking->num_of_days];

        $state['booking_id'] = $bookingId;
        $state['first'] = $first->id;
        $state['second'] = $second->id;

        return $state;
    }

    private function bookingHeader(int $bookingId): array
    {
        $booking = Booking::find($bookingId);

        return [$booking->arrival_date->toDateString(), $booking->departure_date->toDateString(), (int) $booking->num_of_days];
    }

    public function test_restoring_all_rooms_keeps_the_booking_period_matching_the_rooms(): void
    {
        $state = $this->runFlow('2026-09-01');
        $this->assertSame(['2026-09-02', '2026-09-03'], $state['before_delete']);
        // Hủy BK: giai đoạn bao trọn cả phòng đã hủy trước đó.
        $this->assertSame(['2026-09-01', '2026-09-03', 2], $state['after_delete']);

        $list = $this->getJson("/api/bookings/{$state['booking_id']}/cancelled-rooms")->json('data');
        $this->assertTrue(collect($list)->every(fn ($room) => $room['restorable'] === true));

        $this->postJson("/api/bookings/{$state['booking_id']}/restore", ['room_ids' => [$state['first'], $state['second']]])
            ->assertSuccessful();
        $this->assertSame(['2026-09-01', '2026-09-03', 2], $this->bookingHeader($state['booking_id']));
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) BookingRoom::find($state['first'])->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) BookingRoom::find($state['second'])->status);
    }

    public function test_past_room_is_not_selectable_and_period_follows_restored_rooms(): void
    {
        $state = $this->runFlow('2026-09-01', '2026-09-02');
        $this->assertSame(['2026-09-01', '2026-09-03', 2], $state['after_delete']);

        $list = collect($this->getJson("/api/bookings/{$state['booking_id']}/cancelled-rooms")->json('data'))->keyBy('id');
        $this->assertFalse($list[$state['first']]['restorable']);
        $this->assertSame('Ngày đến đã qua ngày hệ thống', $list[$state['first']]['not_restorable_reason']);
        $this->assertTrue($list[$state['second']]['restorable']);

        // Chọn cả phòng đã qua ngày đến: bị chặn và báo đúng phòng.
        $this->postJson("/api/bookings/{$state['booking_id']}/restore", ['room_ids' => [$state['first'], $state['second']]])
            ->assertStatus(422)
            ->assertJsonPath('ineligible_room_ids', [$state['first']]);

        $this->postJson("/api/bookings/{$state['booking_id']}/restore", ['room_ids' => [$state['second']]])
            ->assertSuccessful();
        $this->assertSame(['2026-09-02', '2026-09-03', 1], $this->bookingHeader($state['booking_id']));
        $this->assertSame(BookingRoom::STATUS_CANCELLED, (int) BookingRoom::find($state['first'])->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) BookingRoom::find($state['second'])->status);
    }

    // ---------------------------------------------------------------- S9
    public function test_s9_restore_selected_rooms_is_all_or_nothing(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_DELETED]);
        $roomA = $this->createRoom($booking, 'G4-SA', ['room_number' => '101', 'status' => BookingRoom::STATUS_CANCELLED]);
        $roomB = $this->createRoom($booking, 'G4-SB', ['room_number' => '102', 'status' => BookingRoom::STATUS_CANCELLED]);
        BookingCancelLog::create(['cancel_type' => 'room', 'booking_id' => $booking->id, 'booking_room_id' => $roomA->id, 'cancelled_by_username' => 'a', 'cancelled_at' => '2026-08-05 09:00:00', 'cancelled_by_user_id' => 0]);
        BookingCancelLog::create(['cancel_type' => 'room', 'booking_id' => $booking->id, 'booking_room_id' => $roomB->id, 'cancelled_by_username' => 'b', 'cancelled_at' => '2026-08-06 10:00:00', 'cancelled_by_user_id' => 0]);

        $other = $this->createBooking(['booking_name' => 'Other']);
        $this->createRoom($other, 'G4-OT', ['room_number' => '102']);

        $this->getJson("/api/bookings/{$booking->id}/cancelled-rooms")
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $roomB->id)
            ->assertJsonPath('data.1.id', $roomA->id);

        // Dòng 294: trùng số phòng không loại phòng mà hỏi xác nhận xóa số phòng trùng.
        $this->postJson("/api/bookings/{$booking->id}/restore", ['room_ids' => [$roomA->id, $roomB->id]])
            ->assertOk()
            ->assertJsonPath('needs_duplicate_confirm', true)
            ->assertJsonPath('duplicate_rooms.0.booking_code', $other->booking_code);
        $this->assertSame(BookingRoom::STATUS_CANCELLED, (int) $roomA->fresh()->status);
        $this->assertSame(Booking::STATUS_DELETED, (int) $booking->fresh()->status);

        $this->postJson("/api/bookings/{$booking->id}/restore", ['room_ids' => [$roomA->id]])
            ->assertSuccessful()
            ->assertJsonPath('restored_room_ids', [$roomA->id]);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $roomA->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_CANCELLED, (int) $roomB->fresh()->status);
    }

    public function test_s9_restoring_a_cancelled_room_keeps_an_inhouse_booking_checked_in(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_CHECKIN]);
        $this->createRoom($booking, 'G4-IH', ['room_number' => '101', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $cancelled = $this->createRoom($booking, 'G4-IC', ['arrival_date' => '2026-08-08', 'status' => BookingRoom::STATUS_CANCELLED]);

        $this->postJson("/api/bookings/{$booking->id}/restore", ['room_ids' => [$cancelled->id]])->assertSuccessful();

        $this->assertSame(Booking::STATUS_CHECKIN, (int) $booking->fresh()->status);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $cancelled->fresh()->status);
    }

    // ---------------------------------------------------------------- S10
    public function test_s10_lock_starting_on_departure_day_does_not_block_the_night_before(): void
    {
        RoomLock::create(['room_number' => '101', 'start_date' => '2026-08-08 00:00:00', 'end_date' => '2026-08-20 12:00:00', 'lock_type' => 'OOO', 'is_active' => 1, 'reason' => 'Bảo trì']);

        $vacant = $this->getJson('/api/rooms/vacant?room_class_id=' . $this->roomClass->id . '&arrival_date=2026-08-07&departure_date=2026-08-08')
            ->assertSuccessful()->json('data');
        $this->assertContains('101', collect($vacant)->pluck('room_number')->all());

        $this->postJson('/api/bookings', [
            'booking_name' => 'Walkin',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->regStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'rooms' => [['roomNumber' => '101', 'arrivalDate' => '2026-08-07', 'departureDate' => '2026-08-08', 'price' => 500000]],
            ]],
        ])->assertStatus(201);

        $blocked = $this->getJson('/api/rooms/vacant?room_class_id=' . $this->roomClass->id . '&arrival_date=2026-08-07&departure_date=2026-08-09')
            ->json('data');
        $this->assertNotContains('101', collect($blocked)->pluck('room_number')->all());
    }

    // ---------------------------------------------------------------- S11
    public function test_s11_single_booking_response_contains_child_breakfast_details(): void
    {
        $booking = $this->createBooking();
        $room = $this->createRoom($booking, 'G4-SH');
        $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08']);

        $this->getJson("/api/bookings/{$booking->id}")
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.booking_rooms.0.children.0.breakfast_details')
            ->assertJsonPath('data.booking_rooms.0.children.0.breakfast_details.0.amount', '90000.00');
    }

    public function test_s11_posting_room_charge_also_posts_child_breakfast_once(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_CHECKIN]);
        $room = $this->createRoom($booking, 'G4-PO', ['room_number' => '101', 'status' => BookingRoom::STATUS_CHECKED_IN]);
        $guest = Guest::create(['full_name' => 'Post Guest', 'guest_status' => Guest::STATUS_ACTIVE]);
        BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => 1, 'status' => 1]);
        $child = $this->createChildWithSurcharge($booking, $room, ['2026-08-07', '2026-08-08', '2026-08-09'], 90000, true);

        $payload = ['booking_room_id' => $room->id, 'date_from' => '2026-08-07', 'date_to' => '2026-08-07', 'mode' => 'auto'];
        $this->postJson('/api/booking-room-services/post-room-charge', $payload)->assertSuccessful();
        $this->postJson('/api/booking-room-services/post-room-charge', $payload)->assertSuccessful();

        $bills = ServiceBill::where('RentalRoomId1', $room->id)->where('ServiceId', 'BD')->get();
        $this->assertCount(1, $bills);
        $this->assertSame((string) $child->id, (string) $bills->first()->CustomerId1);
        $this->assertSame(90000.0, (float) $bills->first()->Amount);
        $this->assertSame($room->id, $bills->first()->RentalRoomId2);
        $this->assertStringContainsString('Breakfast Child/Ăn Sáng Trẻ Em', $bills->first()->DescriptionServive);
    }
}
