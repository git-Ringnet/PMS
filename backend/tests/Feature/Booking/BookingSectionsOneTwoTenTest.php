<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingSectionsOneTwoTenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RegistrationStatus $registrationStatus;

    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'booking_sections_user']);
        $role = Role::create([
            'code' => 'booking_sections_test',
            'name' => 'Booking sections test',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        foreach (['fo.booking.create', 'fo.booking.edit', 'fo.payment.create', 'fo.checkin'] as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'FO'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->syncWithoutDetaching([$role->id]);
        $this->actingAs($this->user);

        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => Booking::STATUS_RESERVATION, 'name' => 'Reservation'],
            ['id' => Booking::STATUS_CHECKIN, 'name' => 'Checked In'],
            ['id' => Booking::STATUS_CHECKOUT, 'name' => 'Checked Out'],
            ['id' => Booking::STATUS_DELETED, 'name' => 'Cancelled'],
        ]);
        SystemDateRoll::create([
            'system_date' => '2026-08-07',
            'actual_date' => '2026-08-07',
            'shift' => '1',
            'username' => $this->user->username,
        ]);

        $this->registrationStatus = RegistrationStatus::create([
            'booking_status_id' => 1,
            'name' => 'Tentative',
            'cut_off_day' => 5,
            'is_availability' => true,
        ]);
        DB::table('markets')->insertOrIgnore([
            ['id' => 1, 'name' => 'FIT', 'code' => 'FIT', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customer_sources')->insertOrIgnore([
            ['id' => 1, 'name' => 'Walk-in', 'code' => 'WI', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('companies')->insertOrIgnore([
            ['id' => 1, 'name' => 'Walk-in', 'code' => 'WALK', 'created_at' => now(), 'updated_at' => now()],
        ]);

        PaymentMethod::create(['code' => 'CA', 'name' => 'Tiền mặt', 'payment_group' => 1]);
        PaymentMethod::create(['code' => 'CK', 'name' => 'Chuyển khoản', 'payment_group' => 1]);
        HotelConfig::create(['name' => 'AllowCheckinVacantClean', 'value' => '0']);

        $roomForm = RoomForm::create(['name' => 'Standard']);
        $this->roomClass = RoomClass::create(['code' => 'STD', 'name' => 'Standard', 'is_active' => true]);
        Room::create([
            'room_number' => '105',
            'room_form_id' => $roomForm->id,
            'room_class_id' => $this->roomClass->id,
            'floor' => 1,
            'status' => 'available',
            'room_status_code' => 'vacant_clean',
        ]);
    }

    public function test_editing_deposit_payment_method_refreshes_generated_description(): void
    {
        $booking = $this->makeBooking();
        $room = BookingRoom::create([
            'id' => 'G-SECTIONS-1',
            'booking_id' => $booking->id,
            'room_number' => '105',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'booking_room_id' => $room->id,
            'date' => '2026-08-07',
            'amount' => 100000,
            'description' => 'Deposit (Tiền mặt) - Phòng 105',
            'payment_method_id' => 'CA',
            'pack2' => Payment::PACK2_DEPOSIT,
            'status' => Payment::STATUS_PENDING,
            'edit_flag' => 0,
        ]);

        $this->putJson("/api/payments/{$payment->id}", [
            'payment_method_id' => 'CK',
            'description' => 'Ghi chú cũ',
        ])->assertSuccessful();

        $payment->refresh();
        $this->assertSame('CK', $payment->payment_method_id);
        $this->assertSame('Deposit (Chuyển khoản) - Phòng 105', $payment->description);
    }

    public function test_confirmation_date_is_cut_off_at_system_date_on_create_and_unchanged_on_edit(): void
    {
        $response = $this->postJson('/api/bookings', [
            'booking_name' => 'Confirmation cutoff',
            'arrival_date' => '2026-08-10',
            'departure_date' => '2026-08-11',
            'num_of_days' => 1,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ])->assertSuccessful();

        $booking = Booking::findOrFail($response->json('data.id'));
        $this->assertSame('2026-08-07', $booking->confirm_date->toDateString());

        $this->putJson("/api/bookings/{$booking->id}", [
            'booking_name' => 'Confirmation cutoff edited',
            'arrival_date' => '2026-08-12',
            'departure_date' => '2026-08-13',
            'num_of_days' => 1,
            'confirm_date' => '2026-08-12',
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'company_id' => 1,
            'market_id' => 1,
            'customer_source_id' => 1,
        ])->assertSuccessful();

        $this->assertSame('2026-08-07', $booking->fresh()->confirm_date->toDateString());
    }

    public function test_disallowed_check_in_warning_does_not_expose_configuration_name(): void
    {
        $booking = $this->makeBooking();
        $room = BookingRoom::create([
            'id' => 'G-SECTIONS-2',
            'booking_id' => $booking->id,
            'room_number' => '105',
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        $response = $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Phòng 105 đang ở trạng thái chờ kiểm tra (Vacant Clean). Không thể thực hiện nhận phòng.');
        $this->assertStringNotContainsString('AllowCheckinVacantClean', (string) $response->json('message'));
    }

    private function checkInRoomWithStatus(string $roomNumber, string $statusCode, array $payload = [])
    {
        \App\Models\Room::updateOrCreate(['room_number' => $roomNumber], [
            'room_class_id' => $this->roomClass->id, 'room_form_id' => RoomForm::firstOrCreate(['name' => 'Standard'])->id,
            'floor' => 2, 'room_status_code' => $statusCode,
        ]);
        $booking = $this->makeBooking();
        $room = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => $roomNumber,
            'room_class_id' => $this->roomClass->id,
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'status' => BookingRoom::STATUS_BOOKED,
        ]);

        return [$booking, $room, $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in", $payload)];
    }

    /** Dòng 211: AllowCheckinVacantClean = 1 không cho nhận phòng bẩn / chờ dọn. */
    public function test_row_211_dirty_room_cannot_be_checked_in_even_when_setting_is_one(): void
    {
        \App\Models\HotelConfig::updateOrCreate(['name' => 'AllowCheckinVacantClean'], ['value' => '1']);

        [, , $dirty] = $this->checkInRoomWithStatus('201', 'vacant_dirty');
        $dirty->assertStatus(422)->assertJsonPath('message', 'Phòng 201 đang ở trạng thái chưa dọn (Vacant Dirty). Không thể thực hiện nhận phòng.');

        [, , $turndown] = $this->checkInRoomWithStatus('202', 'turndown', ['confirmed' => true]);
        $turndown->assertStatus(422);
        $this->assertSame('vacant_dirty', \App\Models\Room::where('room_number', '201')->value('room_status_code'));
    }

    /** Dòng 211: phòng chờ kiểm tra hỏi xác nhận (không còn câu "Tình trạng phòng sẽ được giữ nguyên"), xác nhận thì nhận phòng. */
    public function test_row_211_vacant_clean_room_needs_confirmation_then_checks_in(): void
    {
        \App\Models\HotelConfig::updateOrCreate(['name' => 'AllowCheckinVacantClean'], ['value' => '1']);

        [$booking, $room, $first] = $this->checkInRoomWithStatus('203', 'vacant_clean');
        $first->assertOk()
            ->assertJsonPath('needs_confirmation', true)
            ->assertJsonPath('message', 'Phòng 203 đang ở trạng thái chờ kiểm tra (Vacant Clean). Bạn có muốn tiếp tục nhận phòng không?');
        $this->assertSame(BookingRoom::STATUS_BOOKED, (int) $room->fresh()->status);

        $this->patchJson("/api/bookings/{$booking->id}/rooms/{$room->id}/check-in", ['confirmed' => true])->assertSuccessful();
        $this->assertSame(BookingRoom::STATUS_CHECKED_IN, (int) $room->fresh()->status);
    }

    /** Dòng 211: AllowCheckinVacantClean = 0 không cho nhận phòng chờ kiểm tra. */
    public function test_row_211_vacant_clean_room_is_blocked_when_setting_is_zero(): void
    {
        \App\Models\HotelConfig::updateOrCreate(['name' => 'AllowCheckinVacantClean'], ['value' => '0']);

        [, , $response] = $this->checkInRoomWithStatus('204', 'vacant_clean', ['confirmed' => true]);
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Phòng 204 đang ở trạng thái chờ kiểm tra (Vacant Clean). Không thể thực hiện nhận phòng.');
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name' => 'Section booking',
            'arrival_date' => '2026-08-07',
            'departure_date' => '2026-08-08',
            'num_of_days' => 1,
            'booking_date' => '2026-08-07',
            'status' => Booking::STATUS_RESERVATION,
            'registration_status_id' => $this->registrationStatus->booking_status_id,
            'created_by' => $this->user->username,
        ], $attributes));
    }
}
