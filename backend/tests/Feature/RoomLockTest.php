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

        // Dòng 153: quyền mở khóa theo Rule (vai trò), không theo chức danh / bộ phận
        foreach (['adminUser' => 'branch_admin', 'hkmUser' => 'hk_manager', 'fomUser' => 'fo_manager'] as $property => $code) {
            $role = \App\Models\Role::firstOrCreate(['code' => $code], ['name' => $code, 'level' => 2, 'is_active' => true]);
            $this->{$property}->roles()->attach($role->id);
        }

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

    public function test_room_map_unlock_status_change_enforces_role_and_updates_end_date()
    {
        // Cấu hình thông số RoleUserUnlockRoomOOO/OOS
        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'Admin,FOM']);

        // Tạo user không có quyền mở khóa (HK)
        $unauthorizedUser = User::create([
            'name' => 'Housekeeper',
            'username' => 'hk_staff',
            'email' => 'hkstaff@pms.com',
            'job_title' => 'Nhân viên buồng',
            'job_title_code' => 'RL018',
            'department_code' => 'HK',
            'department' => 'BỘ PHẬN BUỒNG PHÒNG',
            'password' => bcrypt('password'),
            'is_active_user' => true,
        ]);

        // Tạo lock trên phòng 101 từ 2026-06-01 đến 2026-06-05
        $lock = RoomLock::create([
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO',
            'status' => 'Active',
            'is_active' => 1,
            'username' => 'admin',
        ]);
        $this->room101->update(['room_status_code' => 'ooo']);

        // 1. Thử mở khóa bằng đổi trạng thái phòng khi không có Role hợp lệ -> Bị chặn 403
        \Laravel\Sanctum\Sanctum::actingAs($unauthorizedUser);
        $resForbidden = $this->putJson("/api/rooms/{$this->room101->id}/status", [
            'room_status_code' => 'vacant_clean',
            'current_module' => 'housekeeping',
        ]);
        $resForbidden->assertStatus(403);
        $this->assertStringContainsString('không thuộc vai trò (Role) được phép mở khóa phòng', $resForbidden->json('message'));

        // 2. Mở khóa bằng user có Role hợp lệ (Admin) -> Thành công
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $resSuccess = $this->putJson("/api/rooms/{$this->room101->id}/status", [
            'room_status_code' => 'vacant_clean',
            'current_module' => 'housekeeping',
        ]);
        $resSuccess->assertStatus(200);

        // Kiểm tra database room_locks: end_date được cập nhật về ngày hệ thống (2026-06-01), status = Done, is_active = 2
        $lock->refresh();
        $this->assertEquals(2, $lock->is_active);
        $this->assertEquals('Done', $lock->status);
        $this->assertEquals($this->adminUser->username, $lock->unlock_username);
        $this->assertStringStartsWith('2026-06-01', $lock->end_date);
    }

    public function test_allow_over_room_type_checked_before_unassignable_and_strictly_blocks_when_zero()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        // Tạo 1 phòng SUPD (đã có room101 ở setUp)
        // Tạo 1 booking chiếm phòng 101 từ 2026-06-10 đến 2026-06-12
        $b = \App\Models\Booking::create([
            'booking_name' => 'Khách chiếm phòng duy nhất',
            'status' => \App\Models\Booking::STATUS_RESERVATION,
            'booking_date' => '2026-06-01',
            'arrival_date' => '2026-06-10',
            'departure_date' => '2026-06-12',
            'registration_status_id' => 1,
            'created_by' => 'admin',
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $b->id,
            'room_class_id' => $this->supdClass->id,
            'room_number' => null, // unassigned
            'arrival_date' => '2026-06-10',
            'departure_date' => '2026-06-12',
            'status' => \App\Models\BookingRoom::STATUS_BOOKED,
        ]);

        // Cấu hình: AllowOverRoomTypeRoomKind = 0 (chặn cứng không cho over), AllowLockRoomCauseUnassignableRoomBK = 1
        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => '0']);
        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '1']);

        // Khóa phòng 101 trong ngày 10-12 dẫn đến AV = 1 - 0 - 1 = 0, khóa thêm 1 phòng làm AV = -1 (< 0)
        // Thử gửi force = true xem có bị chặn cứng không
        $res = $this->postJson('/api/room-locks', [
            'room_number' => $this->room101->room_number,
            'start_date' => '2026-06-10 00:00:00',
            'end_date' => '2026-06-12 12:00:00',
            'lock_type' => 'OOO',
            'reason' => 'Khóa gây over',
            'force' => true,
        ]);

        $res->assertStatus(422);
        // Phải thông báo lỗi AV < 0 chặn cứng trước, không cho phép force
        $this->assertStringContainsString('hết phòng trống (AV < 0)', $res->json('message'));
        $this->assertFalse($res->json('require_confirm') ?? false);
    }

    private function makeUserWithRole(string $username, string $roleCode, ?int $branchId = null): User
    {
        $user = User::create([
            'name' => $username, 'username' => $username, 'email' => $username.'@pms.com',
            'job_title' => 'Trưởng Bộ Phận', 'department_code' => 'FO', 'department' => 'BỘ PHẬN LỄ TÂN',
            'password' => bcrypt('password'), 'is_active_user' => true,
        ]);
        $role = \App\Models\Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode, 'level' => 3, 'is_active' => true]);
        $user->roles()->attach($role->id, ['system_branch_id' => $branchId]);

        return $user;
    }

    /** Dòng 153: RoleUserUnlockRoomOOO/OOS là tên Rule, so khớp chính xác. */
    public function test_row_153_unlock_rule_matches_role_exactly(): void
    {
        $service = app(\App\Services\RoomLockPermissionService::class);
        $foStaff = $this->makeUserWithRole('fo1', 'fo_staff');
        $noRole = User::create(['name' => 'Lễ tân không vai trò', 'username' => 'fo2', 'email' => 'fo2@pms.com', 'department_code' => 'FO',
            'department' => 'BỘ PHẬN LỄ TÂN', 'job_title' => 'FOM', 'password' => bcrypt('x'), 'is_active_user' => true]);
        $lock = ['lock_type' => 'OOO'];

        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'FOM']);
        $this->assertNotNull($service->checkUnlockRolePermission($foStaff, $lock));      // FO staff không phải FOM
        $this->assertNull($service->checkUnlockRolePermission($this->fomUser, $lock));   // fo_manager = FOM
        $this->assertNotNull($service->checkUnlockRolePermission($noRole, $lock));       // chức danh / bộ phận không tính

        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'FO']);
        $this->assertNull($service->checkUnlockRolePermission($foStaff, $lock));
        $this->assertNotNull($service->checkUnlockRolePermission($this->fomUser, $lock)); // FO không bao gồm FOM

        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'hk_manager, fo_staff']);
        $this->assertNull($service->checkUnlockRolePermission($foStaff, $lock));          // ghi thẳng mã vai trò
        $this->assertNull($service->checkUnlockRolePermission($this->hkmUser, $lock));
        $this->assertNotNull($service->checkUnlockRolePermission($this->fomUser, $lock));
    }

    public function test_row_153_unlock_rule_only_counts_roles_of_current_branch(): void
    {
        $service = app(\App\Services\RoomLockPermissionService::class);
        \App\Models\SystemBranch::firstOrCreate(['id' => 1], ['code' => 'HKT1', 'name' => 'Chi nhánh 1', 'is_active' => true]);
        \App\Models\SystemBranch::firstOrCreate(['id' => 2], ['code' => 'HKT2', 'name' => 'Chi nhánh 2', 'is_active' => true]);
        $user = $this->makeUserWithRole('fom_branch2', 'fo_manager', 2);
        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'FOM']);

        $this->assertNotNull($service->checkUnlockRolePermission($user, ['lock_type' => 'OOS'], 1));
        $this->assertNull($service->checkUnlockRolePermission($user, ['lock_type' => 'OOS'], 2));
    }

    public function test_row_153_unlock_api_blocks_user_without_rule(): void
    {
        HotelConfig::updateOrCreate(['name' => 'RoleUserUnlockRoomOOO/OOS'], ['value' => 'FOM']);
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-01 00:00:00', 'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO', 'status' => 'Active', 'username' => 'admin', 'is_active' => 1]);

        \Laravel\Sanctum\Sanctum::actingAs($this->makeUserWithRole('fo1', 'fo_staff'));
        $this->deleteJson("/api/room-locks/{$lock->id}")->assertStatus(403);

        \Laravel\Sanctum\Sanctum::actingAs($this->fomUser);
        $this->deleteJson("/api/room-locks/{$lock->id}")->assertOk();
    }

    private function editPayload(string $start, string $end, string $type = 'OOO'): array
    {
        return ['start_date' => "{$start} 00:00:00", 'end_date' => "{$end} 23:59:00", 'reason' => 'Bảo trì', 'maintenance_percent' => 0,
            'lock_type' => $type, 'is_active' => 1];
    }

    public static function row156EditModes(): array
    {
        return [
            'OOO single' => ['OOO', false],
            'OOS single' => ['OOS', false],
            'OOO batch' => ['OOO', true],
            'OOS batch' => ['OOS', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('row156EditModes')]
    public function test_row_156_red_note_activates_room_on_system_date_in_both_edit_modes(string $type, bool $batch): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        \App\Models\SystemDateRoll::query()->update(['system_date' => '2026-09-24 00:00:00']);
        $this->room101->update(['room_status_code' => 'vacant_ready']);
        $lock = RoomLock::create([
            'room_number' => '101', 'start_date' => '2026-09-25 00:00:00', 'end_date' => '2026-09-27 23:59:59',
            'lock_type' => $type, 'status' => 'New', 'username' => 'admin', 'is_active' => 1,
        ]);
        \Illuminate\Support\Facades\Event::fake([\App\Events\RoomStatusUpdated::class]);

        $save = function (array $payload) use ($batch, $lock) {
            return $batch
                ? $this->postJson('/api/room-locks/bulk-update', ['locks' => [array_merge($payload, ['lock_id' => $lock->id])]])
                : $this->putJson("/api/room-locks/{$lock->id}", $payload);
        };
        // A note-only edit of tomorrow's lock must leave today's room available.
        $save($this->editPayload('2026-09-25', '2026-09-27', $type))->assertOk();
        $this->assertSame('New', $lock->fresh()->status);
        $this->assertSame('vacant_ready', $this->room101->fresh()->room_status_code);
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\RoomStatusUpdated::class);

        $payload = $this->editPayload('2026-09-24', '2026-09-27', $type);
        $payload['reason'] = 'Note đỏ 24/09';
        $payload['maintenance_percent'] = 35;
        $save($payload)->assertOk();
        $this->assertSame('Active', $lock->fresh()->status);
        $this->assertSame('2026-09-24', $lock->fresh()->start_date->toDateString());
        $this->assertSame(strtolower($type), $this->room101->fresh()->room_status_code);
        $this->assertSame('Note đỏ 24/09', $lock->fresh()->reason);
        $this->assertSame(35, $lock->fresh()->maintenance_percent);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\RoomStatusUpdated::class);

        // Once started, changing its start date is forbidden without changing either record.
        $save($this->editPayload('2026-09-25', '2026-09-27', $type))->assertStatus(422);
        $this->assertSame('2026-09-24', $lock->fresh()->start_date->toDateString());
        $this->assertSame('Active', $lock->fresh()->status);
        $this->assertSame(strtolower($type), $this->room101->fresh()->room_status_code);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('row156EditModes')]
    public function test_row_156_red_note_rejects_activation_over_inhouse_booking_without_changing_status(string $type, bool $batch): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        \App\Models\SystemDateRoll::query()->update(['system_date' => '2026-09-24 00:00:00']);
        $this->makeBooking('101', '2026-09-24', '2026-09-26', \App\Models\BookingRoom::STATUS_CHECKED_IN);
        $this->room101->update(['room_status_code' => 'occupied_dirty']);
        $lock = RoomLock::create([
            'room_number' => '101', 'start_date' => '2026-09-25 00:00:00', 'end_date' => '2026-09-27 23:59:59',
            'lock_type' => $type, 'status' => 'New', 'username' => 'admin', 'is_active' => 1,
        ]);
        $payload = $this->editPayload('2026-09-24', '2026-09-27', $type);
        $payload['force'] = true;
        $response = $batch
            ? $this->postJson('/api/room-locks/bulk-update', ['locks' => [array_merge($payload, ['lock_id' => $lock->id])], 'force' => true])
            : $this->putJson("/api/room-locks/{$lock->id}", $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('trùng lịch với booking', $response->json('message'));
        $this->assertSame('2026-09-25', $lock->fresh()->start_date->toDateString());
        $this->assertSame('New', $lock->fresh()->status);
        $this->assertSame('occupied_dirty', $this->room101->fresh()->room_status_code);
    }

    /** Dòng 156: sửa ngày bắt đầu về ngày hệ thống -> khóa có hiệu lực ngay, phòng chuyển OOO/OOS. */
    public function test_row_156_moving_start_to_system_date_activates_lock_and_room_status(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $this->room101->update(['room_status_code' => 'vacant_ready']);
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-03 00:00:00', 'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOS', 'status' => 'New', 'username' => 'admin', 'is_active' => 1]);

        // Chỉ sửa ghi chú, chưa tới ngày khóa -> phòng giữ nguyên
        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-03', '2026-06-05', 'OOS'))->assertOk();
        $this->assertSame('vacant_ready', $this->room101->fresh()->room_status_code);
        $this->assertSame('New', $lock->fresh()->status);

        // Sửa ngày bắt đầu = ngày hệ thống (01/06)
        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-01', '2026-06-05', 'OOS'))->assertOk();
        $this->assertSame('oos', $this->room101->fresh()->room_status_code);
        $this->assertSame('Active', $lock->fresh()->status);
    }

    /** Dòng 156: khóa đang hiệu lực chỉ được sửa ngày kết thúc / ghi chú, không sửa ngày bắt đầu. */
    public function test_row_156_active_lock_allows_end_date_but_not_start_date(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-01 00:00:00', 'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO', 'status' => 'Active', 'username' => 'admin', 'is_active' => 1]);
        $this->room101->update(['room_status_code' => 'ooo']);

        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-01', '2026-06-08'))->assertOk();
        $this->assertStringStartsWith('2026-06-08', (string) $lock->fresh()->end_date);
        $this->assertSame('ooo', $this->room101->fresh()->room_status_code);

        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-02', '2026-06-08'))->assertStatus(422);
    }

    /** Dòng 288: sửa khóa tương lai của phòng đang có khách không được ghi phòng thành OOO. */
    public function test_row_288_editing_future_lock_keeps_occupied_room_status(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $this->room101->update(['room_status_code' => 'occupied_dirty']);
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-10 00:00:00', 'end_date' => '2026-06-12 23:59:59',
            'lock_type' => 'OOO', 'status' => 'New', 'username' => 'admin', 'is_active' => 1]);

        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-10', '2026-06-13'))->assertOk();
        $this->assertSame('occupied_dirty', $this->room101->fresh()->room_status_code);

        $this->postJson('/api/room-locks/bulk-update', ['locks' => [[
            'id' => $lock->id, 'start_date' => '2026-06-11 00:00:00', 'end_date' => '2026-06-13 23:59:00', 'reason' => 'x', 'lock_type' => 'OOO',
        ]]])->assertOk();
        $this->assertSame('occupied_dirty', $this->room101->fresh()->room_status_code);
    }

    private function makeBooking(?string $roomNumber, string $arrival, string $departure, int $status = 0): void
    {
        $booking = \App\Models\Booking::create([
            'booking_name' => 'Khách '.($roomNumber ?? 'chưa gán'), 'status' => \App\Models\Booking::STATUS_RESERVATION,
            'booking_date' => '2026-06-01', 'arrival_date' => $arrival, 'departure_date' => $departure,
            'registration_status_id' => 1, 'created_by' => 'admin',
        ]);
        \App\Models\BookingRoom::create([
            'booking_id' => $booking->id, 'room_class_id' => $this->supdClass->id, 'room_number' => $roomNumber,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'status' => $status,
        ]);
    }

    /** Dòng 156: gia hạn khóa (cả khóa đang hiệu lực và khóa tương lai) bị chặn nếu trùng booking đã đặt. */
    public function test_row_156_extending_lock_is_blocked_by_existing_booking(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $this->makeBooking('101', '2026-06-10', '2026-06-13');

        $active = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-01 00:00:00', 'end_date' => '2026-06-05 23:59:59',
            'lock_type' => 'OOO', 'status' => 'Active', 'username' => 'admin', 'is_active' => 1]);
        $res = $this->putJson("/api/room-locks/{$active->id}", $this->editPayload('2026-06-01', '2026-06-11'));
        $res->assertStatus(422);
        $this->assertStringContainsString('trùng lịch với booking', $res->json('message'));
        $this->assertStringStartsWith('2026-06-05', (string) $active->fresh()->end_date);
    }

    /** Dòng 156: gia hạn khóa làm hết phòng trống theo AllowOverRoomTypeRoomKind. */
    public function test_row_156_extending_lock_checks_allow_over_room_type(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $this->makeBooking(null, '2026-06-10', '2026-06-12'); // hạng SUPD chỉ có 1 phòng (101)
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-05 00:00:00', 'end_date' => '2026-06-08 23:59:59',
            'lock_type' => 'OOO', 'status' => 'New', 'username' => 'admin', 'is_active' => 1]);

        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => '0']);
        $res = $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-05', '2026-06-11'));
        $res->assertStatus(422);
        $this->assertStringContainsString('hết phòng trống (AV < 0)', $res->json('message'));

        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => '1']);
        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '1']);
        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-05', '2026-06-11'))
            ->assertStatus(422)->assertJsonPath('require_confirm', true);
        $this->assertStringStartsWith('2026-06-08', (string) $lock->fresh()->end_date);
    }

    /** Dòng 156: gia hạn khóa làm booking chưa gán số không gán được phòng nguyên giai đoạn (AllowLockRoomCauseUnassignableRoomBK). */
    public function test_row_156_extending_lock_checks_unassignable_booking(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        Room::create(['room_number' => '102', 'room_class_id' => $this->supdClass->id, 'room_form_id' => $this->doubleForm->id,
            'max_guests' => 2, 'floor' => '1', 'status' => 'available']);
        $this->makeBooking('102', '2026-06-15', '2026-06-17');
        $this->makeBooking(null, '2026-06-15', '2026-06-20');
        HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->update(['value' => '1']);
        $lock = RoomLock::create(['room_number' => '101', 'start_date' => '2026-06-10 00:00:00', 'end_date' => '2026-06-12 23:59:59',
            'lock_type' => 'OOO', 'status' => 'New', 'username' => 'admin', 'is_active' => 1]);

        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '0']);
        $res = $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-10', '2026-06-18') + ['force' => true]);
        $res->assertStatus(422);
        $this->assertStringContainsString('không đủ phòng trống liên tục để gán cho booking', $res->json('message'));

        HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->update(['value' => '1']);
        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-10', '2026-06-18'))
            ->assertStatus(422)->assertJsonPath('require_confirm', true);
        $this->putJson("/api/room-locks/{$lock->id}", $this->editPayload('2026-06-10', '2026-06-18') + ['force' => true])->assertOk();
        $this->assertStringStartsWith('2026-06-18', (string) $lock->fresh()->end_date);
    }

    /** Dòng 288: không cho chuyển tay phòng đang có khách sang OOO/OOS. */
    public function test_row_288_cannot_set_lock_status_on_occupied_room(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $this->makeBooking('101', '2026-05-30', '2026-06-03', \App\Models\BookingRoom::STATUS_CHECKED_IN);
        $this->room101->update(['room_status_code' => 'occupied_dirty']);

        $res = $this->putJson("/api/rooms/{$this->room101->id}/status", ['room_status_code' => 'ooo']);
        $res->assertStatus(422);
        $this->assertStringContainsString('đang có khách ở', $res->json('message'));
        $this->postJson('/api/rooms/bulk-status', ['room_ids' => [$this->room101->id], 'room_status_code' => 'oos'])->assertStatus(422);
        $this->assertSame('occupied_dirty', $this->room101->fresh()->room_status_code);

        $this->putJson("/api/rooms/{$this->room101->id}/status", ['room_status_code' => 'occupied_clean'])->assertOk();
    }
}
