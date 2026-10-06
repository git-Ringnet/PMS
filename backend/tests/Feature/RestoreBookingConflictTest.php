<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\Market;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreBookingConflictTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $connections = ['mysql', 'mysql_data', 'mysql_db', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
        foreach ($connections as $conn) {
            config(["database.connections.{$conn}" => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]]);
        }

        return $app;
    }

    private User $user;
    private RoomClass $roomClass;
    private Room $room1;
    private Room $room2;
    private RegistrationStatus $status;
    private Market $market;
    private CustomerSource $customerSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'SystemConfigurationSeeder']);
        $this->artisan('db:seed', ['--class' => 'DepartmentSeeder']);
        $this->artisan('db:seed', ['--class' => 'HotelDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'SpecialRequestSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDateRollSeeder']);
        $this->artisan('db:seed', ['--class' => 'SystemDefinitionSeeder']);
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);

        $this->user = User::factory()->create([
            'username' => 'test_admin',
        ]);
        $role = Role::create([
            'code' => 'booking_test_role',
            'name' => 'Booking Test Role',
            'level' => 3,
            'department_scope' => 'FO',
            'is_active' => true,
        ]);
        $permissionEdit = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'Edit booking', 'module' => 'FO']);
        $role->permissions()->attach([$permissionEdit->id]);
        $this->user->roles()->attach($role->id);

        $this->actingAs($this->user);

        $this->status = RegistrationStatus::where('booking_status_id', 20)->firstOrFail();
        $this->market = Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']);
        $this->customerSource = CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']);

        $this->roomClass = RoomClass::first() ?? RoomClass::create([
            'name'       => 'Suite',
            'code'       => 'SUI',
            'is_active'  => 1,
        ]);

        $roomForm = RoomForm::first() ?? RoomForm::create([
            'name' => 'King',
            'code' => 'K',
        ]);

        // Xóa phòng cũ trùng số phòng hoặc room class nếu có để chuẩn hóa 2 phòng test
        Room::whereIn('room_number', ['106', '107'])->delete();
        Room::where('room_class_id', $this->roomClass->id)->delete();

        $this->room1 = Room::create([
            'room_number'   => '106',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 1,
            'status'        => 0,
            'is_internal'   => 0,
        ]);

        $this->room2 = Room::create([
            'room_number'   => '107',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 1,
            'status'        => 0,
            'is_internal'   => 0,
        ]);
    }

    private function createBooking(array $attrs = []): Booking
    {
        return Booking::create(array_merge([
            'booking_name'           => 'Test Booking',
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'booking_date'           => '2026-10-01',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->id,
            'status'                 => Booking::STATUS_DELETED,
            'created_by'             => $this->user->username,
        ], $attrs));
    }

    /**
     * TH1: Trùng số phòng với booking khác đang hoạt động
     * Báo needs_duplicate_confirm kèm "- R: 106 - BK: GAL..."
     * Chọn Có (clear_duplicate_rooms=true) -> Khôi phục và xóa số phòng bị trùng.
     */
    public function test_restore_booking_conflicts_with_active_booking(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Booking A (đang hoạt động, giữ phòng 106 từ 10/10 đến 12/10)
        $bookingA = $this->createBooking([
            'booking_name' => 'Active Booking',
            'status'       => Booking::STATUS_RESERVATION,
        ]);
        BookingRoom::create([
            'id'            => 'BR-ACT-106',
            'booking_id'    => $bookingA->id,
            'room_number'   => '106',
            'room_class_id' => $this->roomClass->id,
            'arrival_date'  => '2026-10-10',
            'departure_date'=> '2026-10-12',
            'rate'          => 1500000,
            'status'        => BookingRoom::STATUS_BOOKED,
        ]);

        // Booking B (bị hủy, từng giữ phòng 106 từ 10/10 đến 12/10)
        $bookingB = $this->createBooking([
            'booking_name' => 'Cancelled Booking',
            'status'       => Booking::STATUS_DELETED,
        ]);
        $roomB = BookingRoom::create([
            'id'            => 'BR-CAN-106',
            'booking_id'    => $bookingB->id,
            'room_number'   => '106',
            'room_class_id' => $this->roomClass->id,
            'arrival_date'  => '2026-10-10',
            'departure_date'=> '2026-10-12',
            'rate'          => 1500000,
            'status'        => BookingRoom::STATUS_CANCELLED,
        ]);

        // 1. Thao tác khôi phục lần 1 chưa có clear_duplicate_rooms
        $res = $this->postJson("/api/bookings/{$bookingB->id}/restore");
        $res->assertStatus(200);
        $res->assertJson([
            'success'                 => false,
            'needs_duplicate_confirm' => true,
        ]);
        $this->assertStringContainsString('- R: 106 - BK: GAL' . $bookingA->id, $res->json('conflict_lines'));

        // 2. Thao tác khôi phục với clear_duplicate_rooms = true -> thành công, room_number bị xóa về null
        $res2 = $this->postJson("/api/bookings/{$bookingB->id}/restore", [
            'clear_duplicate_rooms' => true,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);

        $roomB->refresh();
        $this->assertNull($roomB->room_number);
        $this->assertEquals(BookingRoom::STATUS_BOOKED, $roomB->status);
    }

    /**
     * TH1b: Trùng số phòng do phòng đang bị khóa OOO/OOS
     * Báo needs_duplicate_confirm kèm "- R: 106" (không có BK)
     */
    public function test_restore_booking_conflicts_with_locked_room(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Khóa OOO phòng 106 từ 10/10 đến 12/10
        RoomLock::create([
            'room_number' => '106',
            'start_date'  => '2026-10-10 12:00:00',
            'end_date'    => '2026-10-12 12:00:00',
            'is_active'   => 1,
            'lock_type'   => 'OOO',
        ]);

        // Booking bị hủy có phòng 106
        $booking = $this->createBooking([
            'booking_name' => 'Cancelled with Lock',
            'status'       => Booking::STATUS_DELETED,
        ]);
        $bRoom = BookingRoom::create([
            'id'            => 'BR-CAN-LOCK-106',
            'booking_id'    => $booking->id,
            'room_number'   => '106',
            'room_class_id' => $this->roomClass->id,
            'arrival_date'  => '2026-10-10',
            'departure_date'=> '2026-10-12',
            'rate'          => 1500000,
            'status'        => BookingRoom::STATUS_CANCELLED,
        ]);

        // Lần 1: Cảnh báo trùng do khóa phòng
        $res = $this->postJson("/api/bookings/{$booking->id}/restore");
        $res->assertStatus(200);
        $res->assertJson([
            'success'                 => false,
            'needs_duplicate_confirm' => true,
        ]);
        $this->assertEquals("- R: 106", trim($res->json('conflict_lines')));

        // Lần 2: Đồng ý xóa số phòng -> khôi phục thành công
        $res2 = $this->postJson("/api/bookings/{$booking->id}/restore", [
            'clear_duplicate_rooms' => true,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);

        $bRoom->refresh();
        $this->assertNull($bRoom->room_number);
    }

    /**
     * TH2: Loại phòng bị over & AllowOverRoomTypeRoomKind = 0 -> Chặn không cho khôi phục
     */
    public function test_restore_booking_overbooking_blocked_when_allow_over_is_zero(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);

        // Loại phòng Suite chỉ có 2 phòng vật lý (106, 107)
        // Tạo booking bị hủy có 3 phòng (cần 3 phòng > 2 phòng trống hiện có -> AV < 0)
        $booking = $this->createBooking([
            'booking_name' => 'Over Booking',
            'status'       => Booking::STATUS_DELETED,
        ]);
        for ($i = 1; $i <= 3; $i++) {
            BookingRoom::create([
                'id'            => "BR-OVER-{$i}",
                'booking_id'    => $booking->id,
                'room_number'   => null,
                'room_class_id' => $this->roomClass->id,
                'arrival_date'  => '2026-10-10',
                'departure_date'=> '2026-10-12',
                'rate'          => 1500000,
                'status'        => BookingRoom::STATUS_CANCELLED,
            ]);
        }

        $res = $this->postJson("/api/bookings/{$booking->id}/restore");
        $res->assertStatus(422);
        $res->assertJson([
            'success'         => false,
            'blocked_by_over' => true,
            'message'         => 'Loại phòng đang bị over không thể khôi phục booking',
        ]);
    }

    /**
     * TH2: Loại phòng bị over & AllowOverRoomTypeRoomKind = 1
     * Bật cảnh báo needs_over_confirm: "Loại phòng đang bị over bạn có muốn tiếp tục"
     * Chọn Có (force_over = true) -> Khôi phục thành công
     */
    public function test_restore_booking_overbooking_allowed_with_confirm_when_allow_over_is_one(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Tạo booking có 3 phòng Suite (vượt quá 2 phòng có sẵn)
        $booking = $this->createBooking([
            'booking_name' => 'Over Booking Allowed',
            'status'       => Booking::STATUS_DELETED,
        ]);
        for ($i = 1; $i <= 3; $i++) {
            BookingRoom::create([
                'id'            => "BR-OVER-ALLOW-{$i}",
                'booking_id'    => $booking->id,
                'room_number'   => null,
                'room_class_id' => $this->roomClass->id,
                'arrival_date'  => '2026-10-10',
                'departure_date'=> '2026-10-12',
                'rate'          => 1500000,
                'status'        => BookingRoom::STATUS_CANCELLED,
            ]);
        }

        // Lần 1: Cảnh báo over
        $res = $this->postJson("/api/bookings/{$booking->id}/restore");
        $res->assertStatus(200);
        $res->assertJson([
            'success'            => false,
            'needs_over_confirm' => true,
            'message'            => 'Loại phòng đang bị over bạn có muốn tiếp tục',
        ]);

        // Lần 2: Gửi force_over = true -> khôi phục thành công
        $res2 = $this->postJson("/api/bookings/{$booking->id}/restore", [
            'force_over' => true,
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['success' => true]);

        $booking->refresh();
        $this->assertEquals(Booking::STATUS_RESERVATION, $booking->status);
    }

    /**
     * Kịch bản kết hợp: Vừa trùng số phòng vừa bị Over loại phòng
     * Bước 1: Hỏi xác nhận xóa số phòng bị trùng
     * Bước 2: Hỏi xác nhận over phòng
     * Bước 3: Hoàn tất khôi phục
     */
    public function test_restore_booking_both_duplicate_room_and_overbooking(): void
    {
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Khóa phòng 106
        RoomLock::create([
            'room_number' => '106',
            'start_date'  => '2026-10-10 12:00:00',
            'end_date'    => '2026-10-12 12:00:00',
            'is_active'   => 1,
            'lock_type'   => 'OOO',
        ]);

        // Booking hủy có 3 phòng (1 phòng mang số 106, 2 phòng unassigned)
        $booking = $this->createBooking([
            'booking_name' => 'Combined Conflict Booking',
            'status'       => Booking::STATUS_DELETED,
        ]);
        $roomConflicted = BookingRoom::create([
            'id'            => 'BR-COMB-106',
            'booking_id'    => $booking->id,
            'room_number'   => '106',
            'room_class_id' => $this->roomClass->id,
            'arrival_date'  => '2026-10-10',
            'departure_date'=> '2026-10-12',
            'rate'          => 1500000,
            'status'        => BookingRoom::STATUS_CANCELLED,
        ]);
        for ($i = 2; $i <= 3; $i++) {
            BookingRoom::create([
                'id'            => "BR-COMB-{$i}",
                'booking_id'    => $booking->id,
                'room_number'   => null,
                'room_class_id' => $this->roomClass->id,
                'arrival_date'  => '2026-10-10',
                'departure_date'=> '2026-10-12',
                'rate'          => 1500000,
                'status'        => BookingRoom::STATUS_CANCELLED,
            ]);
        }

        // Bước 1: Trùng phòng 106 do bị khóa
        $step1 = $this->postJson("/api/bookings/{$booking->id}/restore");
        $step1->assertStatus(200);
        $step1->assertJson(['needs_duplicate_confirm' => true]);

        // Bước 2: Đồng ý xóa số phòng trùng (clear_duplicate_rooms=true), tiếp tục gặp cảnh báo over
        $step2 = $this->postJson("/api/bookings/{$booking->id}/restore", [
            'clear_duplicate_rooms' => true,
        ]);
        $step2->assertStatus(200);
        $step2->assertJson(['needs_over_confirm' => true]);

        // Bước 3: Đồng ý tiếp tục over (force_over=true, clear_duplicate_rooms=true) -> thành công
        $step3 = $this->postJson("/api/bookings/{$booking->id}/restore", [
            'clear_duplicate_rooms' => true,
            'force_over'            => true,
        ]);
        $step3->assertStatus(200);
        $step3->assertJson(['success' => true]);

        $roomConflicted->refresh();
        $this->assertNull($roomConflicted->room_number);
    }
}
