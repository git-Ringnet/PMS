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

class BookingAllocationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RoomForm $roomForm;
    private RegistrationStatus $registrationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'allocation_test_user']);
        $role = Role::create([
            'code' => 'allocation_consistency_test',
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
            'system_date' => '2026-08-07 00:00:00',
            'actual_date' => '2026-08-07 00:00:00',
            'shift' => '1',
            'username' => 'allocation_test_user',
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
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'booking_date' => '2026-08-07',
            'created_by' => 'allocation_test_user',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
        ], $attributes));
    }

    private function updatePayload(Booking $booking, array $allocations): array
    {
        return [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => $allocations,
        ];
    }

    public function test_reallocation_quantity_ignores_history_and_creates_requested_current_rooms(): void
    {
        $booking = $this->createBooking();
        $cancelled = BookingRoom::create([
            'id' => 'G-ALLOC-CANCELLED',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
        $moved = BookingRoom::create([
            'id' => 'G-ALLOC-MOVED',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_MOVED,
        ]);

        $details = array_fill(0, 5, [
            'roomNumber' => null,
            'guestName' => 'Reallocated Guest',
        ]);
        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 5,
                'price' => 100000,
                'rooms' => $details,
            ]])
        );

        $response->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $cancelled->id,
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $moved->id,
            'status' => BookingRoom::STATUS_MOVED,
        ]);
        $this->assertSame(
            5,
            BookingRoom::where('booking_id', $booking->id)
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->count()
        );
    }

    public function test_zero_quantity_history_does_not_trigger_wrong_room_type_validation(): void
    {
        $jst = RoomClass::create([
            'code' => 'JST',
            'name' => 'Junior Suite',
            'is_active' => true,
        ]);
        $this->createPhysicalRoom('201', $jst);

        $booking = $this->createBooking();
        $cancelledJst = BookingRoom::create([
            'id' => 'G-ALLOC-JST-CANCELLED',
            'booking_id' => $booking->id,
            'room_class_id' => $jst->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['roomNumber' => null, 'guestName' => 'FAM Guest']],
                ],
                [
                    'roomClassId' => $jst->id,
                    'quantity' => 0,
                    'rooms' => [['bookingRoomId' => $cancelledJst->id]],
                ],
            ])
        );

        $response->assertSuccessful();
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $cancelledJst->id,
            'status' => BookingRoom::STATUS_CANCELLED,
        ]);
    }

    public function test_partial_inhouse_update_counts_only_new_demand_and_preserves_inhouse_room(): void
    {
        $booking = $this->createBooking(['status' => Booking::STATUS_CHECKIN]);
        $inhouse = BookingRoom::create([
            'id' => 'G-ALLOC-INHOUSE',
            'booking_id' => $booking->id,
            'room_number' => '101',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'rooms' => [
                    ['bookingRoomId' => $inhouse->id, 'roomNumber' => '101'],
                    ['roomNumber' => null, 'guestName' => 'Additional Guest'],
                ],
            ]])
        );

        $response->assertSuccessful();
        $inhouse->refresh();
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $inhouse->status);
        $this->assertSame(
            1,
            BookingRoom::where('booking_id', $booking->id)
                ->where('status', BookingRoom::STATUS_BOOKED)
                ->count()
        );
    }

    public function test_persisted_booking_room_id_cannot_be_reused_across_allocation_rows(): void
    {
        $booking = $this->createBooking();
        $booked = BookingRoom::create([
            'id' => 'G-ALLOC-DUPLICATE',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->putJson(
            "/api/bookings/{$booking->id}",
            $this->updatePayload($booking, [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['bookingRoomId' => $booked->id]],
                ],
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'rooms' => [['bookingRoomId' => $booked->id]],
                ],
            ])
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('không được lặp lại', (string) $response->json('message'));
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $booked->id,
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    public function test_new_multi_room_booking_reaches_availability_without_hotel_setting_method_error(): void
    {
        $classes = [];
        foreach (['SUPD', 'SUPT', 'SUPTR'] as $index => $code) {
            $roomClass = RoomClass::create([
                'code' => $code,
                'name' => "Regression {$code}",
                'is_active' => true,
            ]);
            $classes[] = $roomClass;

            foreach (range(1, 2) as $roomIndex) {
                $this->createPhysicalRoom(
                    (string) (($index + 2) * 100 + $roomIndex),
                    $roomClass
                );
            }
        }

        $allocations = array_map(function (RoomClass $roomClass): array {
            return [
                'roomClassId' => $roomClass->id,
                'quantity' => 2,
                'price' => 100000,
                'rooms' => array_fill(0, 2, [
                    'roomClassId' => $roomClass->id,
                    'roomNumber' => null,
                    'guestName' => 'eee regression guest',
                    'arrivalDate' => '2026-08-09',
                    'departureDate' => '2026-08-10',
                ]),
            ];
        }, $classes);

        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'eeee',
            'arrival_date' => '2026-08-09',
            'departure_date' => '2026-08-10',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => $allocations,
        ]);

        $response->assertSuccessful();
        $booking = Booking::where('booking_name', 'eeee')->firstOrFail();
        $this->assertSame(6, BookingRoom::where('booking_id', $booking->id)->count());
        foreach ($classes as $roomClass) {
            $this->assertSame(
                2,
                BookingRoom::where('booking_id', $booking->id)
                    ->where('room_class_id', $roomClass->id)
                    ->where('status', BookingRoom::STATUS_BOOKED)
                    ->count()
            );
        }
    }

    public function test_new_booking_rejects_room_allocation_before_pms_system_date(): void
    {
        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'Past Allocation Booking',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'price' => 100000,
                'rooms' => [[
                    'roomNumber' => null,
                    'arrivalDate' => '2026-08-06',
                    'departureDate' => '2026-08-07',
                ]],
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Ngày đến của phòng nhỏ hơn ngày hệ thống, vui lòng kiểm tra lại thông tin.',
            ]);
        $this->assertDatabaseMissing('bookings', ['booking_name' => 'Past Allocation Booking']);
    }

    public function test_add_only_rejects_past_room_allocation_atomically(): void
    {
        $booking = $this->createBooking();
        $response = $this->postJson("/api/bookings/{$booking->id}/add-rooms", [
            'intent' => 'add_only',
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 100000,
                    'rooms' => [[
                        'arrivalDate' => '2026-08-07',
                        'departureDate' => '2026-08-08',
                    ]],
                ],
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity' => 1,
                    'price' => 100000,
                    'rooms' => [[
                        'arrivalDate' => '2026-08-06',
                        'departureDate' => '2026-08-07',
                    ]],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Ngày đến của phòng nhỏ hơn ngày hệ thống, vui lòng kiểm tra lại thông tin.',
            ]);
        $this->assertSame(0, BookingRoom::where('booking_id', $booking->id)->count());
    }

    public function test_update_rejects_new_past_allocation_and_restores_existing_rooms_atomically(): void
    {
        $booking = $this->createBooking();
        $existingRoom = BookingRoom::create([
            'id' => 'G-ALLOC-UPDATE-EXISTING',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 2,
                'rooms' => [
                    [
                        'bookingRoomId' => $existingRoom->id,
                        'arrivalDate' => '2026-08-07',
                        'departureDate' => '2026-08-08',
                    ],
                    [
                        'arrivalDate' => '2026-08-06',
                        'departureDate' => '2026-08-07',
                    ],
                ],
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Ngày đến của phòng nhỏ hơn ngày hệ thống, vui lòng kiểm tra lại thông tin.',
            ]);
        $this->assertSame(1, BookingRoom::where('booking_id', $booking->id)->count());
        $this->assertSame('2026-08-07', $existingRoom->fresh()->arrival_date->toDateString());
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $existingRoom->fresh()->status);
    }
    private function grantCheckoutMetadataRole(): void
    {
        $role = Role::create([
            'code' => 'checkout_metadata_editor',
            'name' => 'Checkout metadata editor',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $this->user->roles()->attach($role->id);
        HotelConfig::updateOrCreate(
            ['name' => 'RoleUserUpdateCheckoutBooking'],
            ['value' => 'checkout_metadata_editor']
        );
    }

    public function test_authorized_checkout_role_can_update_only_booking_metadata_without_touching_rooms_or_bills(): void
    {
        $this->grantCheckoutMetadataRole();
        $booking = $this->createBooking([
            'status' => Booking::STATUS_CHECKOUT,
            'booking_name' => 'Checked out before',
            'company_id' => 1,
            'payment_value' => 250000,
        ]);
        $room = BookingRoom::create([
            'id' => 'G-CHECKOUT-METADATA-ROOM',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'rate' => 200000,
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'date' => '2026-08-07',
            'amount' => 250000,
            'pack2' => Payment::PACK2_DEPOSIT,
            'status' => Payment::STATUS_PAID,
            'edit_flag' => 0,
            'username' => 'allocation_test_user',
        ]);
        $bill = ServiceBill::create([
            'RegisterId1' => $booking->id,
            'RegisterID2' => $booking->id,
            'RentalRoomId1' => $room->id,
            'RentalRoomId2' => $room->id,
            'ServiceId' => 'RM',
            'Date' => '2026-08-07 12:00:00',
            'OpenTime' => '12:00',
            'Guest' => 'Test guest',
            'DepartmentId' => 'FO',
            'Username' => 'allocation_test_user',
            'Amount' => 200000,
            'Quantity' => 1,
            'Status' => 1,
            'Edit' => 0,
        ]);

        $roomSnapshot = $room->fresh()->getRawOriginal();
        $billSnapshot = ServiceBill::findOrFail($bill->Ma)->getRawOriginal();
        $paymentSnapshot = Payment::findOrFail($payment->id)->getRawOriginal();

        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Checked out updated',
            'customer_source_id' => 1,
            'booker_id' => null,
            'note' => 'Metadata note',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', Booking::STATUS_CHECKOUT)
            ->assertJsonPath('data.booking_name', 'Checked out updated')
            ->assertJsonPath('data.note', 'Metadata note');
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CHECKOUT,
            'booking_name' => 'Checked out updated',
            'customer_source_id' => 1,
            'note' => 'Metadata note',
            'company_id' => 1,
            'payment_value' => 250000,
        ]);
        $this->assertSame('2026-08-07', $booking->fresh()->arrival_date->toDateString());
        $this->assertSame('2026-08-08', $booking->fresh()->departure_date->toDateString());
        $this->assertDatabaseHas('booking_rooms', [
            'id' => $room->id,
            'booking_id' => $booking->id,
            'rate' => 200000,
            'status' => BookingRoom::STATUS_CHECKED_OUT,
        ]);
        $this->assertDatabaseHas('service_bills', ['Ma' => $bill->Ma, 'Amount' => 200000]);
        $this->assertSame(1, ServiceBill::where('RegisterId1', $booking->id)->count());
        $this->assertSame($roomSnapshot, $room->fresh()->getRawOriginal());
        $this->assertSame($billSnapshot, ServiceBill::findOrFail($bill->Ma)->getRawOriginal());
        $this->assertEqualsCanonicalizing($paymentSnapshot, Payment::findOrFail($payment->id)->getRawOriginal());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->user->id,
            'action' => 'Modify',
            'module' => 'reservation',
            'target_id' => (string) $booking->id,
        ]);
    }

    public function test_checkout_update_requires_exact_configured_role_and_rejects_forbidden_fields(): void
    {
        $booking = $this->createBooking([
            'status' => Booking::STATUS_CHECKOUT,
            'booking_name' => 'Original checkout name',
        ]);

        $this->putJson("/api/bookings/{$booking->id}", ['booking_name' => 'Denied'])
            ->assertForbidden();
        $this->assertSame('Original checkout name', $booking->fresh()->booking_name);

        $this->grantCheckoutMetadataRole();
        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Must not persist',
            'arrival_date' => '2026-08-09',
        ])->assertStatus(422);

        $this->assertSame('Original checkout name', $booking->fresh()->booking_name);
        $this->assertSame('2026-08-07', $booking->fresh()->arrival_date->toDateString());
        $this->assertSame(Booking::STATUS_CHECKOUT, (int) $booking->fresh()->status);
    }

    public function test_deleted_booking_stays_blocked_even_for_checkout_metadata_role(): void
    {
        $this->grantCheckoutMetadataRole();
        $booking = $this->createBooking([
            'status' => Booking::STATUS_DELETED,
            'booking_name' => 'Deleted booking',
        ]);

        $this->putJson("/api/bookings/{$booking->id}", ['booking_name' => 'No'])
            ->assertStatus(422);
        $this->assertSame('Deleted booking', $booking->fresh()->booking_name);
        $this->assertSame(Booking::STATUS_DELETED, (int) $booking->fresh()->status);
    }
    public function test_existing_historical_reservation_room_remains_editable(): void
    {
        $booking = $this->createBooking([
            'arrival_date' => '2026-08-06',
            'departure_date' => '2026-08-07',
        ]);
        $room = BookingRoom::create([
            'id' => 'G-ALLOC-HISTORICAL',
            'booking_id' => $booking->id,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-06',
            'departure_date' => '2026-08-07',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => $booking->booking_name,
            'arrival_date' => '2026-08-06',
            'departure_date' => '2026-08-07',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id,
                'quantity' => 1,
                'rooms' => [['bookingRoomId' => $room->id]],
            ]],
        ]);

        $response->assertSuccessful();
        $this->assertSame('2026-08-06', $room->fresh()->arrival_date->toDateString());
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $room->fresh()->status);
    }
}
