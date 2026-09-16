<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\RoomLock;
use App\Models\HotelConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $hkmUser;
    protected User $fomUser;
    protected Room $room101;
    protected RoomClass $supdClass;
    protected RoomForm $doubleForm;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@pms.com',
            'job_title' => 'Tổng giám đốc',
            'job_title_code' => 'RL001',
            'department_code' => 'MGMT',
            'department' => 'BỘ PHẬN QUẢN LÝ',
            'password' => bcrypt('password'),
            'is_active_user' => true,
        ]);

        $this->hkmUser = User::create([
            'name' => 'Housekeeping Manager',
            'username' => 'hkm',
            'email' => 'hkm@pms.com',
            'job_title' => 'Trưởng HK',
            'job_title_code' => 'RL016',
            'department_code' => 'HK',
            'department' => 'BỘ PHẬN BUỒNG PHÒNG',
            'password' => bcrypt('password'),
            'is_active_user' => true,
        ]);

        $this->fomUser = User::create([
            'name' => 'Front Office Manager',
            'username' => 'thaovy',
            'email' => 'fom@pms.com',
            'job_title' => 'Trưởng Bộ Phận',
            'job_title_code' => 'RL017',
            'department_code' => 'FO',
            'department' => 'BỘ PHẬN LỄ TÂN',
            'password' => bcrypt('password'),
            'is_active_user' => true,
        ]);

        // 2. Seed room class and form
        $this->supdClass = RoomClass::create([
            'name' => 'Superior Double',
            'code' => 'SUPD',
            'is_active' => true,
        ]);

        $this->doubleForm = RoomForm::create([
            'name' => 'Double',
            'max_adults' => 2,
        ]);

        // 3. Seed rooms
        $this->room101 = Room::create([
            'room_number' => '101',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '1',
            'status' => 'available',
        ]);

        // 4. Seed configs & system date
        $this->seed(\Database\Seeders\BookingStatusSeeder::class);
        $this->seed(\Database\Seeders\SystemDefinitionSeeder::class);

        \App\Models\SystemDateRoll::create([
            'system_date' => '2026-06-01 00:00:00',
            'actual_date' => '2026-06-01 00:00:00',
            'shift' => '1',
            'username' => 'admin',
        ]);

        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowLockRoomCauseUnassignableRoomBK'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOOCheckDepartment'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOSCheckDepartment'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'OOORoleUserUnlock'], ['value' => 'Admin,FOM,Sales,HKM']);
        HotelConfig::updateOrCreate(['name' => 'OOSRoleUserUnlock'], ['value' => 'Admin,FOM,Sales,HKM']);
        HotelConfig::updateOrCreate(['name' => 'FrmOOO_DefineLockByTime'], ['value' => '23:59']);
    }

    public function test_lock_period_validation()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 12:00:00',
            'end_date' => '2026-07-02 11:00:00', // end < start
            'lock_type' => 'OOO',
            'reason' => 'Bảo trì',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Giờ kết thúc không được nhỏ hơn giờ bắt đầu (trong cùng ngày).'
        ]);
    }

    public function test_overlap_locks_not_allowed()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Create first lock
        RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 10:00:00',
            'end_date' => '2026-07-02 12:00:00',
            'lock_type' => 'OOO',
            'is_active' => 1,
        ]);

        // Attempt overlapping lock
        $response = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-07-02 11:00:00',
            'end_date' => '2026-07-02 13:00:00',
            'lock_type' => 'OOS',
            'reason' => 'Chồng chéo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'
        ]);
    }

    public function test_booking_overlap_prevention_strict()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $room401 = Room::create([
            'room_number' => '401',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '4',
            'status' => 'available',
        ]);

        $booking = \App\Models\Booking::create([
            'booking_code' => 'GAL5333',
            'booking_name' => 'Test Booking',
            'booking_date' => '2026-06-09 00:00:00',
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'created_by' => 'admin',
            'status' => 0,
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '401',
            'room_class_id' => $this->supdClass->id,
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'status' => 0,
        ]);

        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '0']);

        $response = $this->postJson('/api/room-locks', [
            'room_number' => $room401->room_number,
            'start_date' => '2026-06-10 10:00:00',
            'end_date' => '2026-06-12 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Trùng lịch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép khóa phòng vì trùng lịch với booking BK-1 (09/06/2026 ~ 13/06/2026).'
        ]);
    }

    public function test_booking_overlap_prevention_warning()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $room401 = Room::create([
            'room_number' => '401_warn',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '4',
            'status' => 'available',
        ]);

        $booking = \App\Models\Booking::create([
            'booking_name' => 'Test Booking Warn',
            'booking_date' => '2026-06-09 00:00:00',
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'created_by' => 'admin',
            'status' => 0,
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $booking->id,
            'room_number' => '401_warn',
            'room_class_id' => $this->supdClass->id,
            'arrival_date' => '2026-06-09 00:00:00',
            'departure_date' => '2026-06-13 00:00:00',
            'status' => 0,
        ]);

        // Strict block is enforced for booking overlap
        $response = $this->postJson('/api/room-locks', [
            'room_number' => $room401->room_number,
            'start_date' => '2026-06-10 10:00:00',
            'end_date' => '2026-06-12 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Trùng lịch cảnh báo',
        ]);

        $response->assertStatus(422);
    }

    public function test_edit_lock_start_date_restrictions()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo phòng khóa có ngày bắt đầu <= ngày hệ thống (2026-06-01)
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO',
            'is_active' => true,
        ]);

        // Thử sửa start_date của phòng đang active -> Phải bị chặn 422
        $response = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => '2026-06-02 00:00:00', // đổi start_date
            'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa (ngày bắt đầu <= ngày hệ thống).'
        ]);

        // Cho phép sửa end_date, lý do, % bảo trì
        $response2 = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-08 23:59:59',
            'reason' => 'Đổi kế hoạch bảo trì',
            'maintenance_percent' => 50,
            'lock_type' => 'OOO',
        ]);

        $response2->assertStatus(200);
        $this->assertDatabaseHas('room_locks', [
            'id' => $lock->id,
            'reason' => 'Đổi kế hoạch bảo trì',
            'maintenance_percent' => 50,
        ]);
    }

    public function test_edit_future_lock_allows_start_date_modification_above_system_date()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo phòng khóa tương lai (ngày bắt đầu 2026-06-15 > ngày hệ thống 2026-06-01)
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-15 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
            'status' => 'New',
            'is_active' => true,
        ]);

        // 1. Sửa ngày bắt đầu thành một ngày tương lai khác (2026-06-16) -> Thành công
        $response = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => '2026-06-16 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
            'reason' => 'Dời lịch sửa',
            'maintenance_percent' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('room_locks', [
            'id' => $lock->id,
            'start_date' => '2026-06-16 00:00:00',
            'reason' => 'Dời lịch sửa',
        ]);

        // 2. Thử sửa ngày bắt đầu nhỏ hơn ngày hệ thống (2026-05-30 < 2026-06-01) -> Bị chặn 422
        $responseFail = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => '2026-05-30 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
        ]);

        $responseFail->assertStatus(422);
        $this->assertStringContainsString('không được nhỏ hơn Ngày hệ thống', $responseFail->json('message'));
    }

    public function test_unlock_department_permission_checking()
    {
        // Setup: Locked by thaovy (BỘ PHẬN LỄ TÂN)
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO',
            'username' => 'thaovy',
            'is_active' => true,
        ]);

        // Enable department verification
        HotelConfig::where('name', 'OOOCheckDepartment')->update(['value' => '1']);

        // Try unlocking as hkmUser (BỘ PHẬN BUỒNG PHÒNG) -> should fail
        \Laravel\Sanctum\Sanctum::actingAs($this->hkmUser);
        $response = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Bạn không thuộc bộ phận đã thực hiện khóa phòng này (Bộ phận: BỘ PHẬN LỄ TÂN), không thể mở khóa.'
        ]);

        // Try unlocking as adminUser (BỘ PHẬN QUẢN LÝ) -> should fail because of department mismatch
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $response2 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response2->assertStatus(403);

        // Disable department verification -> should pass (assuming role is OK)
        HotelConfig::where('name', 'OOOCheckDepartment')->update(['value' => '0']);
        $response3 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response3->assertStatus(200);
    }

    public function test_unlock_role_permission_checking()
    {
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO',
            'username' => 'admin',
            'is_active' => true,
        ]);

        // Set allowed roles to HKM only
        HotelConfig::where('name', 'OOORoleUserUnlock')->update(['value' => 'HKM']);

        // Try unlocking as FOM (thaovy) -> should fail
        \Laravel\Sanctum\Sanctum::actingAs($this->fomUser);
        $response = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Tài khoản của bạn không thuộc vai trò (Role) được phép mở khóa phòng (Vai trò yêu cầu: HKM).'
        ]);

        // Try unlocking as HKM -> should pass
        \Laravel\Sanctum\Sanctum::actingAs($this->hkmUser);
        $response2 = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response2->assertStatus(200);
    }

    public function test_edit_past_lock_restrictions()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo khóa trong quá khứ so với ngày hệ thống (2026-06-01)
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-05-20 00:00:00',
            'end_date' => '2026-05-25 23:59:59',
            'lock_type' => 'OOO',
            'is_active' => true,
        ]);

        // Sửa khóa đã kết thúc trong quá khứ -> Phải báo lỗi 422
        $response = $this->putJson("/api/room-locks/{$lock->id}", [
            'start_date' => '2026-05-20 00:00:00',
            'end_date' => '2026-05-28 23:59:59',
            'lock_type' => 'OOO',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không được phép chỉnh sửa lịch khóa phòng đã kết thúc trong quá khứ so với ngày hệ thống.'
        ]);
    }

    public function test_section_1_unlock_updates_end_date_to_system_date()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-10 23:59:59',
            'lock_type' => 'OOO',
            'status' => 'Active',
            'is_active' => true,
        ]);

        // Mở khóa phòng
        $response = $this->deleteJson("/api/room-locks/{$lock->id}");
        $response->assertStatus(200);

        $lock->refresh();
        $this->assertEquals(2, $lock->is_active);
        // end_date phải có ngày bắt đầu bằng ngày hệ thống (2026-06-01)
        $this->assertStringStartsWith('2026-06-01', $lock->end_date->format('Y-m-d'));
    }

    public function test_section_4_bulk_update_atomic_transaction()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo 2 phòng khóa
        $lock1 = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-05 00:00:00',
            'end_date' => '2026-06-08 23:59:59',
            'lock_type' => 'OOO',
            'reason' => 'Bảo trì ban đầu 1',
            'is_active' => true,
        ]);

        $room102 = Room::create([
            'room_number' => '102',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '1',
            'status' => 'available',
        ]);

        $lock2 = RoomLock::create([
            'room_number' => $room102->room_number,
            'start_date' => '2026-06-05 00:00:00',
            'end_date' => '2026-06-08 23:59:59',
            'lock_type' => 'OOS',
            'reason' => 'Bảo trì ban đầu 2',
            'is_active' => true,
        ]);

        // 1. Bulk update thành công
        $response = $this->postJson('/api/room-locks/bulk-update', [
            'locks' => [
                [
                    'lock_id' => $lock1->id,
                    'start_date' => '2026-06-06 00:00:00',
                    'end_date' => '2026-06-09 23:59:59',
                    'reason' => 'Cập nhật lý do 1',
                    'maintenance_percent' => 30,
                ],
                [
                    'lock_id' => $lock2->id,
                    'start_date' => '2026-06-06 00:00:00',
                    'end_date' => '2026-06-09 23:59:59',
                    'reason' => 'Cập nhật lý do 2',
                    'maintenance_percent' => 60,
                ],
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('room_locks', ['id' => $lock1->id, 'reason' => 'Cập nhật lý do 1', 'maintenance_percent' => 30]);
        $this->assertDatabaseHas('room_locks', ['id' => $lock2->id, 'reason' => 'Cập nhật lý do 2', 'maintenance_percent' => 60]);

        // 2. Rollback khi 1 trong các dòng có ngày kết thúc < ngày bắt đầu
        $responseFail = $this->postJson('/api/room-locks/bulk-update', [
            'locks' => [
                [
                    'lock_id' => $lock1->id,
                    'start_date' => '2026-06-06 00:00:00',
                    'end_date' => '2026-06-10 23:59:59',
                    'reason' => 'Lý do mới nếu thành công',
                    'maintenance_percent' => 100,
                ],
                [
                    'lock_id' => $lock2->id,
                    'start_date' => '2026-06-06 00:00:00',
                    'end_date' => '2026-06-04 23:59:59', // lỗi: end < start
                    'reason' => 'Lý do 2 lỗi',
                    'maintenance_percent' => 0,
                ],
            ]
        ]);

        $responseFail->assertStatus(422);
        // Đảm bảo lock1 KHÔNG bị đổi thành 'Lý do mới nếu thành công' (atomic rollback)
        $this->assertDatabaseMissing('room_locks', ['id' => $lock1->id, 'reason' => 'Lý do mới nếu thành công']);
    }

    public function test_section_2_allow_lock_room_cause_unassignable_room_bk()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo thêm phòng 102 cùng hạng SUPD (tổng cộng có 2 phòng: 101 và 102)
        $room102 = Room::create([
            'room_number' => '102',
            'room_class_id' => $this->supdClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '1',
            'status' => 'available',
        ]);

        // Booking 1: Đã gán phòng 102 ở từ 2026-06-15 đến 2026-06-17
        $bookingAssigned = \App\Models\Booking::create([
            'booking_name' => 'Khách Phòng 102',
            'status' => \App\Models\Booking::STATUS_RESERVATION,
            'booking_date' => '2026-06-01',
            'arrival_date' => '2026-06-15',
            'departure_date' => '2026-06-17',
            'registration_status_id' => 1,
            'created_by' => 'admin',
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $bookingAssigned->id,
            'room_class_id' => $this->supdClass->id,
            'room_number' => '102',
            'arrival_date' => '2026-06-15',
            'departure_date' => '2026-06-17',
            'status' => \App\Models\BookingRoom::STATUS_BOOKED,
        ]);

        // Booking 2: Chưa gán phòng (unassigned) ở từ 2026-06-15 đến 2026-06-20
        $bookingUnassigned = \App\Models\Booking::create([
            'booking_name' => 'Khách Chưa Gán Phòng',
            'status' => \App\Models\Booking::STATUS_RESERVATION,
            'booking_date' => '2026-06-01',
            'arrival_date' => '2026-06-15',
            'departure_date' => '2026-06-20',
            'registration_status_id' => 1,
            'created_by' => 'admin',
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $bookingUnassigned->id,
            'room_class_id' => $this->supdClass->id,
            'room_number' => null, // Chưa gán phòng
            'arrival_date' => '2026-06-15',
            'departure_date' => '2026-06-20',
            'status' => \App\Models\BookingRoom::STATUS_BOOKED,
        ]);

        // Trường hợp 1: AllowLockRoomCauseUnassignableRoomBK = 0 -> Chặn cứng không cho khóa phòng 101 từ 18 đến 20
        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '0']);

        $resBlocked = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-18 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
            'reason' => 'Sửa phòng',
        ]);

        $resBlocked->assertStatus(422);
        $this->assertStringContainsString('không đủ phòng trống liên tục để gán cho booking', $resBlocked->json('message'));

        // Trường hợp 2: AllowLockRoomCauseUnassignableRoomBK = 1 -> Cảnh báo yêu cầu xác nhận
        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '1']);

        $resWarning = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-18 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
            'reason' => 'Sửa phòng',
        ]);

        $resWarning->assertStatus(422);
        $this->assertTrue($resWarning->json('require_confirm'));

        // Trường hợp 3: Khi người dùng bấm tiếp tục (force = true) -> Cho phép khóa thành công
        $resForce = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-18 00:00:00',
            'end_date' => '2026-06-20 23:59:59',
            'lock_type' => 'OOO',
            'reason' => 'Sửa phòng',
            'force' => true,
        ]);

        $resForce->assertStatus(201);
        $this->assertDatabaseHas('room_locks', [
            'room_number' => $this->room101->room_number,
            'reason' => 'Sửa phòng',
            'is_active' => true,
        ]);
    }

    public function test_multiple_unassigned_bookings_trigger_unassignable_violation()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Giả sử có hạng phòng Suite với 3 phòng vật lý: JST_A, JST_B, JST_C
        $jstClass = \App\Models\RoomClass::firstOrCreate(
            ['code' => 'JST_TEST'],
            ['name' => 'Suite Test', 'orders' => 99]
        );

        Room::create([
            'room_number' => 'JST_A',
            'room_class_id' => $jstClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '12',
            'status' => 'available',
        ]);
        Room::create([
            'room_number' => 'JST_B',
            'room_class_id' => $jstClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '13',
            'status' => 'available',
        ]);
        Room::create([
            'room_number' => 'JST_C',
            'room_class_id' => $jstClass->id,
            'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2,
            'floor' => '14',
            'status' => 'available',
        ]);

        // Phòng JST_C đã gán booking ở đêm 11 (out ngày 12)
        $bAssigned = \App\Models\Booking::create([
            'booking_name' => "Khách JST_C",
            'status' => \App\Models\Booking::STATUS_RESERVATION,
            'booking_date' => '2026-06-01',
            'arrival_date' => '2026-06-11',
            'departure_date' => '2026-06-12',
            'registration_status_id' => 1,
            'created_by' => 'admin',
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $bAssigned->id,
            'room_class_id' => $jstClass->id,
            'room_number' => 'JST_C',
            'arrival_date' => '2026-06-11',
            'departure_date' => '2026-06-12',
            'status' => \App\Models\BookingRoom::STATUS_BOOKED,
        ]);

        // Tạo 2 booking unassigned cho hạng phòng JST từ ngày 11 đến 13 (2 đêm)
        for ($i = 1; $i <= 2; $i++) {
            $b = \App\Models\Booking::create([
                'booking_name' => "Khách JST $i",
                'status' => \App\Models\Booking::STATUS_RESERVATION,
                'booking_date' => '2026-06-01',
                'arrival_date' => '2026-06-11',
                'departure_date' => '2026-06-13',
                'registration_status_id' => 1,
                'created_by' => 'admin',
            ]);
            \App\Models\BookingRoom::create([
                'booking_id' => $b->id,
                'room_class_id' => $jstClass->id,
                'room_number' => null, // unassigned
                'arrival_date' => '2026-06-11',
                'departure_date' => '2026-06-13',
                'status' => \App\Models\BookingRoom::STATUS_BOOKED,
            ]);
        }

        // Khóa phòng JST_A vào đêm 12 (12/06 -> 13/06).
        // AV ngày 11: 3 - 3 = 0 (>= 0).
        // AV ngày 12: 3 - 1 (lock) - 2 (unassigned) = 0 (>= 0, không âm phòng).
        // Nhưng chỉ có JST_B là trống cả 2 đêm liên tục, không đủ cho cả 2 booking unassigned!
        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '0']);

        $res = $this->postJson('/api/room-locks', [
            'room_number' => 'JST_A',
            'start_date' => '2026-06-12 00:00:00',
            'end_date' => '2026-06-13 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Bảo trì suite',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('không đủ phòng trống liên tục để gán cho booking', $res->json('message'));
    }
}
