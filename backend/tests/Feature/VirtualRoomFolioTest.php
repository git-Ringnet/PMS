<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use App\Http\Controllers\Api\NightAuditController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ServiceBill;
use Tests\TestCase;

class VirtualRoomFolioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->user = User::factory()->create(['username' => 'virtual_folio_test']);
        $role = Role::create([
            'code' => 'virtual_folio_test',
            'name' => 'Virtual folio test',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permissions = collect([
            ['fo.checkout', 'Checkout'],
            ['fo.service.add', 'Add service'],
            ['fo.service.edit', 'Edit service'],
            ['fo.booking.edit', 'Edit booking'],
            ['fo.booking.create', 'Create booking'],
            ['fo.booking.cancel', 'Cancel booking'],
            ['fo.payment.create', 'Create payment'],
        ])->map(fn (array $permission) => Permission::firstOrCreate(
            ['code' => $permission[0]],
            ['name' => $permission[1], 'module' => 'FO']
        ));
        $role->permissions()->attach($permissions->pluck('id')->all());
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        RegistrationStatus::create([
            'id' => 1,
            'booking_status_id' => 1,
            'name' => 'Guaranteed',
            'is_availability' => true,
        ]);
        $this->roomClass = RoomClass::create([
            'code' => 'VFT',
            'name' => 'Virtual folio test room class',
            'is_active' => true,
        ]);
    }

    public function test_stay_only_scope_keeps_unassigned_physical_reservations_and_excludes_virtual_folios(): void
    {
        $unassignedBooking = $this->makeBooking();
        $unassignedRoom = $this->makeBookingRoom($unassignedBooking, 'G-VFT-UNASSIGNED', null);

        $serviceBooking = $this->makeBooking(['is_service_only' => true]);
        $serviceRoom = $this->makeBookingRoom($serviceBooking, 'G-VFT-SERVICE', null);

        $form = RoomForm::create(['name' => 'Virtual test form', 'max_adults' => 2]);
        $virtualInventoryRoom = Room::create([
            'room_number' => '001',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => false,
        ]);
        $numberBasedBooking = $this->makeBooking();
        $numberBasedRoom = $this->makeBookingRoom($numberBasedBooking, 'G-VFT-NUMBER', $virtualInventoryRoom->room_number);

        $internalInventoryRoom = Room::create([
            'room_number' => 'INT-VFT',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => true,
        ]);
        $internalBooking = $this->makeBooking();
        $internalRoom = $this->makeBookingRoom($internalBooking, 'G-VFT-INTERNAL', $internalInventoryRoom->room_number);

        $stayIds = BookingRoom::stayOnly()->pluck('id')->all();

        $this->assertContains($unassignedRoom->id, $stayIds);
        $this->assertNotContains($serviceRoom->id, $stayIds);
        $this->assertNotContains($numberBasedRoom->id, $stayIds);
        $this->assertNotContains($internalRoom->id, $stayIds);
        $this->assertTrue($numberBasedRoom->fresh()->isVirtual());
        $this->assertTrue($internalRoom->fresh()->isVirtual());
    }

    public function test_checkout_api_rejects_service_only_folio_without_changing_its_status(): void
    {
        $booking = $this->makeBooking(['is_service_only' => true]);
        $room = $this->makeBookingRoom($booking, 'G-VFT-CHECKOUT', null, BookingRoom::STATUS_CHECKED_IN);

        $this->postJson("/api/booking-rooms/{$room->id}/checkout", ['guest_ids' => ['guest-not-linked']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'virtual_room');

        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $room->fresh()->status);
    }

    public function test_booking_index_keeps_legacy_default_and_filters_only_when_stay_only_is_requested(): void
    {
        $physicalBooking = $this->makeBooking();
        $physicalRoom = $this->makeBookingRoom($physicalBooking, 'G-VFT-LIST-PHYSICAL', null);
        $serviceBooking = $this->makeBooking(['is_service_only' => true]);
        $serviceRoom = $this->makeBookingRoom($serviceBooking, 'G-VFT-LIST-SERVICE', null);

        $defaultRows = $this->getJson('/api/bookings?with_billing=true')
            ->assertSuccessful()
            ->json('data');
        $this->assertEqualsCanonicalizing([$physicalBooking->id, $serviceBooking->id], collect($defaultRows)->pluck('id')->all());
        $serviceDefaultRow = collect($defaultRows)->firstWhere('id', $serviceBooking->id);
        $this->assertSame([$serviceRoom->id], collect($serviceDefaultRow['booking_rooms'])->pluck('id')->all());

        $stayRows = $this->getJson('/api/bookings?with_billing=true&stay_only=true')
            ->assertSuccessful()
            ->json('data');
        $this->assertSame([$physicalBooking->id], collect($stayRows)->pluck('id')->all());
        $this->assertSame([$physicalRoom->id], collect($stayRows[0]['booking_rooms'])->pluck('id')->all());
    }

    public function test_checkout_can_list_virtual_room_masters_without_creating_bookings(): void
    {
        $form = RoomForm::create(['name' => 'Virtual listing form', 'max_adults' => 2]);
        Room::create([
            'room_number' => '001-VFT-LIST',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => true,
        ]);
        Room::create([
            'room_number' => '002-VFT-LIST',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => false,
        ]);
        Room::create([
            'room_number' => '101-VFT-LIST',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '1',
            'is_internal' => false,
        ]);

        $response = $this->getJson('/api/rooms?virtual_only=true&include_inactive=true')->assertSuccessful();
        $numbers = collect($response->json('data'))->pluck('room_number')->all();

        $this->assertContains('001-VFT-LIST', $numbers);
        $this->assertContains('002-VFT-LIST', $numbers);
        $this->assertNotContains('101-VFT-LIST', $numbers);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingRoom::count());
    }

    public function test_internal_virtual_room_folio_is_created_lazily_and_idempotently(): void
    {
        $form = RoomForm::create(['name' => 'Virtual folio form', 'max_adults' => 2]);
        Room::create([
            'room_number' => '0INT-VFT-LAZY',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => true,
        ]);

        $first = $this->postJson('/api/rooms/0INT-VFT-LAZY/service-folio')
            ->assertSuccessful()
            ->assertJsonPath('data.created', true);
        $bookingId = $first->json('data.booking_id');
        $bookingRoomId = $first->json('data.booking_room_id');

        $this->assertTrue((bool) Booking::findOrFail($bookingId)->is_service_only);
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) BookingRoom::findOrFail($bookingRoomId)->status);
        $this->assertSame(0.0, (float) BookingRoom::findOrFail($bookingRoomId)->rate);

        $second = $this->postJson('/api/rooms/0INT-VFT-LAZY/service-folio')
            ->assertSuccessful()
            ->assertJsonPath('data.created', false);

        $this->assertSame($bookingId, $second->json('data.booking_id'));
        $this->assertSame($bookingRoomId, $second->json('data.booking_room_id'));
        $this->assertSame(1, BookingRoom::where('room_number', '0INT-VFT-LAZY')->count());
    }

    public function test_unflagged_zero_prefixed_room_is_visible_but_not_auto_provisioned(): void
    {
        $form = RoomForm::create(['name' => 'Virtual unverified form', 'max_adults' => 2]);
        Room::create([
            'room_number' => '003-VFT-UNFLAGGED',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => false,
        ]);
        Room::create([
            'room_number' => 'INT-VFT-UNPREFIXED',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => true,
        ]);

        $this->postJson('/api/rooms/003-VFT-UNFLAGGED/service-folio')
            ->assertStatus(422)
            ->assertJsonPath('code', 'virtual_room_configuration_required');
        $this->postJson('/api/rooms/INT-VFT-UNPREFIXED/service-folio')
            ->assertStatus(422)
            ->assertJsonPath('code', 'virtual_room_configuration_required');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingRoom::count());
    }

    public function test_service_only_folio_rejects_lodging_mutations(): void
    {
        $booking = $this->makeBooking(['is_service_only' => true]);

        $this->putJson("/api/bookings/{$booking->id}", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');

        $this->postJson("/api/bookings/{$booking->id}/add-rooms", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');

        $this->postJson("/api/bookings/{$booking->id}/rooms", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');

        $this->postJson("/api/bookings/{$booking->id}/rooms/bulk-update", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');
        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');
        $this->postJson("/api/bookings/{$booking->id}/copy", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');
        $this->postJson("/api/bookings/{$booking->id}/restore", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');
        $this->postJson("/api/bookings/{$booking->id}/revert-noshow", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'service_only_folio');

        $this->assertSame(0, $booking->bookingRooms()->count());
        $this->assertNotNull(Booking::find($booking->id));
    }

    public function test_deposit_transfer_rejects_virtual_targets_but_accepts_a_stay_booking(): void
    {
        $form = RoomForm::create(['name' => 'Virtual deposit form', 'max_adults' => 2]);
        $virtualRoom = Room::create([
            'room_number' => '004-VFT-DEPOSIT',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '0',
            'is_internal' => true,
        ]);
        $virtualBooking = $this->makeBooking(['is_service_only' => true]);
        $this->makeBookingRoom($virtualBooking, 'G-VFT-VIRTUAL-DEPOSIT', $virtualRoom->room_number);

        $mixedBooking = $this->makeBooking();
        $mixedVirtualRoom = $this->makeBookingRoom($mixedBooking, 'G-VFT-MIXED-VIRTUAL', $virtualRoom->room_number);
        $physicalRoom = Room::create([
            'room_number' => '104-VFT-DEPOSIT',
            'room_form_id' => $form->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => '1',
            'is_internal' => false,
        ]);
        $physicalBooking = $this->makeBooking();
        $this->makeBookingRoom($physicalBooking, 'G-VFT-PHYSICAL-DEPOSIT', $physicalRoom->room_number);
        $sourceBooking = $this->makeBooking();

        $toServiceOnlyPayment = Payment::create([
            'booking_id' => $sourceBooking->id,
            'date' => now()->toDateString(),
            'amount' => 100,
            'pack2' => Payment::PACK2_DEPOSIT,
            'folio_id' => 1,
            'status' => Payment::STATUS_PENDING,
            'edit_flag' => 0,
        ]);
        $this->postJson('/api/payments/transfer', [
            'payment_ids' => [$toServiceOnlyPayment->id],
            'target_booking_id' => $virtualBooking->id,
        ])->assertStatus(422);
        $this->assertSame(0, (int) $toServiceOnlyPayment->fresh()->edit_flag);

        $toVirtualRoomPayment = Payment::create([
            'booking_id' => $sourceBooking->id,
            'date' => now()->toDateString(),
            'amount' => 200,
            'pack2' => Payment::PACK2_DEPOSIT,
            'folio_id' => 1,
            'status' => Payment::STATUS_PENDING,
            'edit_flag' => 0,
        ]);
        $this->postJson('/api/payments/transfer', [
            'payment_ids' => [$toVirtualRoomPayment->id],
            'target_booking_id' => $mixedBooking->id,
            'target_room_id' => $mixedVirtualRoom->id,
        ])->assertStatus(422);
        $this->assertSame(0, (int) $toVirtualRoomPayment->fresh()->edit_flag);

        $toPhysicalBookingPayment = Payment::create([
            'booking_id' => $sourceBooking->id,
            'date' => now()->toDateString(),
            'amount' => 300,
            'pack2' => Payment::PACK2_DEPOSIT,
            'folio_id' => 1,
            'status' => Payment::STATUS_PENDING,
            'edit_flag' => 0,
        ]);
        $this->postJson('/api/payments/transfer', [
            'payment_ids' => [$toPhysicalBookingPayment->id],
            'target_booking_id' => $physicalBooking->id,
        ])->assertSuccessful();
        $this->assertSame(1, (int) $toPhysicalBookingPayment->fresh()->edit_flag);
    }

    public function test_virtual_folio_migration_backfill_is_conservative(): void
    {
        $originalConnection = config('database.default');
        $migrationConnection = 'virtual_folio_migration_test';
        config([
            "database.connections.{$migrationConnection}" => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:', 'prefix' => '']
            ),
            'database.default' => $migrationConnection,
        ]);
        DB::purge($migrationConnection);

        try {
            Schema::create('bookings', fn ($table) => $table->id());
            Schema::create('rooms', function ($table): void {
                $table->string('room_number')->primary();
                $table->boolean('is_internal')->default(false);
            });
            Schema::create('booking_rooms', function ($table): void {
                $table->string('id')->primary();
                $table->unsignedBigInteger('booking_id');
                $table->string('room_number')->nullable();
                $table->unsignedBigInteger('room_class_id');
                $table->timestamp('deleted_at')->nullable();
            });

            DB::table('bookings')->insert(array_map(fn ($id) => ['id' => $id], [1, 2, 3, 4, 5]));
            DB::table('rooms')->insert([
                ['room_number' => '001', 'is_internal' => false],
                ['room_number' => 'INT-1', 'is_internal' => true],
                ['room_number' => '101', 'is_internal' => false],
                ['room_number' => '002', 'is_internal' => false],
            ]);
            DB::table('booking_rooms')->insert([
                ['id' => 'VIRTUAL', 'booking_id' => 1, 'room_number' => '001', 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'INTERNAL', 'booking_id' => 2, 'room_number' => 'INT-1', 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'MIXED-VIRTUAL', 'booking_id' => 3, 'room_number' => '001', 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'MIXED-PHYSICAL', 'booking_id' => 3, 'room_number' => '101', 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'UNASSIGNED', 'booking_id' => 4, 'room_number' => null, 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'VIRTUAL-DELETED-MIX', 'booking_id' => 5, 'room_number' => '002', 'room_class_id' => 1, 'deleted_at' => null],
                ['id' => 'DELETED-PHYSICAL', 'booking_id' => 5, 'room_number' => '101', 'room_class_id' => 1, 'deleted_at' => '2026-01-01 00:00:00'],
            ]);

            $migration = require database_path('migrations/2026_09_28_130000_add_service_only_virtual_folio_support.php');
            $migration->up();

            $serviceOnlyIds = DB::table('bookings')
                ->where('is_service_only', true)
                ->orderBy('id')
                ->pluck('id')
                ->all();
            $this->assertSame([1, 2], $serviceOnlyIds);
            $this->assertTrue(Schema::hasColumn('bookings', 'is_service_only'));
            $this->assertTrue(Schema::hasColumn('booking_rooms', 'room_class_id'));
        } finally {
            DB::disconnect($migrationConnection);
            DB::purge($migrationConnection);
            config(['database.default' => $originalConnection]);
        }
    }

    public function test_night_audit_does_not_post_room_charge_or_close_virtual_anchor(): void
    {
        SystemDateRoll::create([
            'system_date' => '2026-09-29 00:00:00',
            'actual_date' => '2026-09-29 00:00:00',
            'shift' => '1',
            'username' => $this->user->username,
        ]);
        $booking = $this->makeBooking(['is_service_only' => true]);
        $room = $this->makeBookingRoom($booking, 'G-VFT-NIGHT-AUDIT', null, BookingRoom::STATUS_CHECKED_IN);

        $response = app(NightAuditController::class)->runNightAudit(Request::create('/api/night-audit/run', 'POST', [
            'occupied_to_dirty' => false,
            'empty_to_inspect' => false,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData()->success);
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $room->fresh()->status);
        $this->assertSame('3018-01-01', $room->fresh()->departure_date->toDateString());
        $this->assertSame(0, ServiceBill::where('RegisterId1', $booking->id)
            ->where('RentalRoomId1', $room->id)
            ->where('ServiceId', 'RM')
            ->count());
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Virtual folio test',
            'arrival_date' => '2018-01-01',
            'departure_date' => '3018-01-01',
            'num_of_days' => 1,
            'booking_date' => '2018-01-01',
            'created_by' => $this->user->username,
            'status' => Booking::STATUS_CHECKIN,
            'registration_status_id' => 1,
        ], $attributes));
    }

    private function makeBookingRoom(Booking $booking, string $id, ?string $roomNumber, int $status = BookingRoom::STATUS_BOOKED): BookingRoom
    {
        return BookingRoom::create([
            'id' => $id,
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => null,
            'arrival_date' => '2018-01-01',
            'departure_date' => '3018-01-01',
            'status' => $status,
            'rate' => 0,
        ]);
    }
}
