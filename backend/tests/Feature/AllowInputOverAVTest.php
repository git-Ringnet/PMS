<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\HotelConfig;
use App\Models\Market;
use App\Models\Permission;
use App\Models\RegistrationStatus;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllowInputOverAVTest extends TestCase
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
    private RegistrationStatus $status;
    private Market $market;
    private CustomerSource $customerSource;
    private Company $company;

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
        $permissionCreate = Permission::firstOrCreate(['code' => 'fo.booking.create'], ['name' => 'Create booking', 'module' => 'FO']);
        $permissionEdit = Permission::firstOrCreate(['code' => 'fo.booking.edit'], ['name' => 'Edit booking', 'module' => 'FO']);
        $role->permissions()->attach([$permissionCreate->id, $permissionEdit->id]);
        $this->user->roles()->attach($role->id);

        $this->actingAs($this->user);

        $this->status = RegistrationStatus::where('booking_status_id', 20)->firstOrFail();
        $this->market = Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']);
        $this->customerSource = CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']);
        $this->company = Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);

        $this->roomClass = RoomClass::create([
            'name'       => 'Test Class Over',
            'code'       => 'TCO',
            'is_active'  => 1,
        ]);

        $roomForm = RoomForm::first() ?? RoomForm::create([
            'name' => 'King',
            'code' => 'K',
        ]);

        // Tạo đúng 1 phòng vật lý
        $this->room1 = Room::create([
            'room_number'   => 'TEST_999',
            'room_class_id' => $this->roomClass->id,
            'room_form_id'  => $roomForm->id,
            'floor'         => 9,
            'is_active'     => 1,
            'status'        => 'clean',
        ]);
    }

    public function test_hotel_setting_returns_allow_input_over_av()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '1', 'is_visible' => true]);

        $res = $this->getJson('/api/hotel-settings');
        $res->assertOk();
        $res->assertJsonPath('data.AllowInputOverAV', '1');
    }

    public function test_cannot_create_booking_when_allow_input_over_av_is_0_and_av_is_0()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Tạo 1 booking chiếm trọn phòng TEST_999
        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK Existing',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Tạo booking thứ 2 với số lượng = 1 cho cùng loại phòng -> AV = 0 -> Phải bị chặn
        $res = $this->postJson('/api/bookings', [
            'booking_name'           => 'BK Over Test',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
            'room_allocations'       => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity'    => 1,
                    'rooms'       => [
                        [
                            'roomClassId'   => $this->roomClass->id,
                            'arrivalDate'   => '2026-10-10',
                            'departureDate' => '2026-10-12',
                        ]
                    ]
                ]
            ]
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Không đủ phòng trống', $res->json('message') ?? '');
    }

    public function test_can_create_booking_when_allow_input_over_av_is_1_and_allow_over_room_type_is_1()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Tạo 1 booking chiếm trọn phòng TEST_999
        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK Existing',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Khi AllowInputOverAV = 1 và AllowOverRoomTypeRoomKind = 1 -> Cho phép tạo booking dẫn đến over
        $res = $this->postJson('/api/bookings', [
            'booking_name'           => 'BK Over Allowed',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
            'room_allocations'       => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity'    => 1,
                    'rooms'       => [
                        [
                            'roomClassId'   => $this->roomClass->id,
                            'arrivalDate'   => '2026-10-10',
                            'departureDate' => '2026-10-12',
                        ]
                    ]
                ]
            ]
        ]);

        $res->assertCreated();
    }

    public function test_cannot_add_room_to_booking_when_allow_input_over_av_is_0_and_av_is_0()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        // Booking 1 chiếm phòng TEST_999
        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK 1',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Thêm phòng vào booking khi AV = 0 -> Bị chặn
        $res = $this->postJson("/api/bookings/{$bk1->id}/rooms", [
            'room_class_id'  => $this->roomClass->id,
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'rate'           => 500000,
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Không còn phòng trống cho loại phòng này', $res->json('message') ?? '');
    }

    public function test_can_add_room_to_booking_when_allow_input_over_av_is_1_and_allow_over_is_1()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK 1',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Khi AllowInputOverAV = 1 và AllowOverRoomTypeRoomKind = 1 -> Cho phép thêm phòng
        $res = $this->postJson("/api/bookings/{$bk1->id}/rooms", [
            'room_class_id'  => $this->roomClass->id,
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'rate'           => 500000,
        ]);

        $res->assertCreated();
    }

    public function test_cannot_batch_add_rooms_when_allow_input_over_av_is_0_and_av_is_0()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK Add Rooms',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Thêm phòng qua endpoint /add-rooms (được tab Lấy phòng gọi) khi AV = 0 -> Bị chặn
        $res = $this->postJson("/api/bookings/{$bk1->id}/add-rooms", [
            'intent'           => 'add_only',
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity'    => 1,
                    'price'       => 500000,
                    'rooms'       => [
                        [
                            'roomClassId'   => $this->roomClass->id,
                            'arrivalDate'   => '2026-10-10',
                            'departureDate' => '2026-10-12',
                            'price'         => 500000,
                        ]
                    ]
                ]
            ]
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Không đủ phòng trống', $res->json('message') ?? '');
    }

    public function test_can_batch_add_rooms_when_allow_input_over_av_is_1_and_allow_over_is_1()
    {
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '1']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        $bk1 = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK Add Rooms Allowed',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
        ]);
        BookingRoom::create([
            'booking_id'     => $bk1->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => 'TEST_999',
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'status'         => BookingRoom::STATUS_BOOKED,
        ]);

        // Thêm phòng qua endpoint /add-rooms khi AllowInputOverAV = 1 -> Thành công
        $res = $this->postJson("/api/bookings/{$bk1->id}/add-rooms", [
            'intent'           => 'add_only',
            'room_allocations' => [
                [
                    'roomClassId' => $this->roomClass->id,
                    'quantity'    => 1,
                    'price'       => 500000,
                    'rooms'       => [
                        [
                            'roomClassId'   => $this->roomClass->id,
                            'arrivalDate'   => '2026-10-10',
                            'departureDate' => '2026-10-12',
                            'price'         => 500000,
                        ]
                    ]
                ]
            ]
        ]);

        $res->assertOk();
    }

    public function test_other_operations_like_restoring_cancelled_booking_can_still_over_when_allow_over_is_1_and_allow_input_over_is_0()
    {
        // Nghiệp vụ: AllowOverRoomTypeRoomKind = 1 cho phép over, nhưng AllowInputOverAV = 0
        // Thao tác khôi phục booking (không phải tạo booking mới hay lấy phòng) VẪN ĐƯỢC PHÉP dẫn đến âm phòng
        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '1']);

        $bkCancelled = Booking::create([
            'created_by'             => $this->user->username,
            'booking_date'           => '2026-10-06',
            'booking_name'           => 'BK Restore Over',
            'company_id'             => $this->company->id,
            'arrival_date'           => '2026-10-10',
            'departure_date'         => '2026-10-12',
            'num_of_days'            => 2,
            'registration_status_id' => $this->status->booking_status_id,
            'market_id'              => $this->market->id,
            'customer_source_id'     => $this->customerSource->id,
            'status'                 => Booking::STATUS_DELETED,
        ]);
        // Booking bị hủy có 2 phòng trong khi khách sạn chỉ có 1 phòng vật lý TEST_999 -> Over
        BookingRoom::create([
            'id'             => 'BR-RESTORE-1',
            'booking_id'     => $bkCancelled->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => null,
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'rate'           => 500000,
            'status'         => BookingRoom::STATUS_CANCELLED,
        ]);
        BookingRoom::create([
            'id'             => 'BR-RESTORE-2',
            'booking_id'     => $bkCancelled->id,
            'room_class_id'  => $this->roomClass->id,
            'room_number'    => null,
            'arrival_date'   => '2026-10-10',
            'departure_date' => '2026-10-12',
            'rate'           => 500000,
            'status'         => BookingRoom::STATUS_CANCELLED,
        ]);

        // Gửi force_over: true để khôi phục booking over
        $res = $this->postJson("/api/bookings/{$bkCancelled->id}/restore", [
            'force_over' => true,
        ]);

        $res->assertOk();
        $this->assertEquals(Booking::STATUS_RESERVATION, $bkCancelled->fresh()->status);
    }
}
