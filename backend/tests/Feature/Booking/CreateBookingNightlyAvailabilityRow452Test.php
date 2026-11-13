<?php

namespace Tests\Feature\Booking;

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

/**
 * Dòng 452: tạo đăng ký lấy phòng chỉ kiểm tra số phòng trống (AV) của LOẠI phòng theo từng đêm (lấy đêm thấp nhất),
 * không đòi phải có đủ phòng vật lý trống liên tục nguyên giai đoạn. Số phòng gán sau, nhân viên tự canh chỉnh.
 *
 * Loại phòng có 5 phòng. 4 booking khác đã gán số phòng, mỗi đêm giữ 2 phòng nhưng đổi phòng giữa chừng:
 * mỗi đêm còn 3 phòng trống (AV = 3) nhưng chỉ 1 phòng (Z505) trống liên tục cả giai đoạn.
 */
class CreateBookingNightlyAvailabilityRow452Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private RoomClass $roomClass;
    private RegistrationStatus $status;
    private Market $market;
    private CustomerSource $source;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SystemConfigurationSeeder', 'DepartmentSeeder', 'HotelDefinitionSeeder', 'SpecialRequestSeeder', 'SystemDateRollSeeder', 'SystemDefinitionSeeder', 'BookingStatusSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => $seeder]);
        }

        $this->user = User::factory()->withRole('branch_admin')->create(['username' => 'row452']);
        $role = Role::create(['code' => 'row452_role', 'name' => 'Row 452', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        $permission = Permission::firstOrCreate(['code' => 'fo.booking.create'], ['name' => 'Create booking', 'module' => 'FO']);
        $role->permissions()->attach([$permission->id]);
        $this->user->roles()->attach($role->id);
        $this->actingAs($this->user);

        $this->status = RegistrationStatus::where('booking_status_id', 20)->firstOrFail();
        $this->market = Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']);
        $this->source = CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']);
        $this->company = Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);

        HotelConfig::updateOrCreate(['name' => 'AllowInputOverAV'], ['value' => '0']);
        HotelConfig::updateOrCreate(['name' => 'AllowOverRoomTypeRoomKind'], ['value' => '0']);

        $this->roomClass = RoomClass::create(['name' => 'Family 452', 'code' => 'F452', 'is_active' => 1]);
        $form = RoomForm::first() ?? RoomForm::create(['name' => 'King', 'code' => 'K']);
        foreach (['Z501', 'Z502', 'Z503', 'Z504', 'Z505'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->roomClass->id, 'room_form_id' => $form->id, 'floor' => 15, 'is_active' => 1, 'status' => 'clean']);
        }

        // Đêm 07-08: giữ Z501 + Z503. Đêm 10-11: giữ Z502 + Z504. Đêm 09 trống hết. => mỗi đêm AV >= 3, chỉ Z505 trống liên tục.
        $this->holdRoom('Z501', '2026-10-07', '2026-10-09');
        $this->holdRoom('Z503', '2026-10-07', '2026-10-09');
        $this->holdRoom('Z502', '2026-10-10', '2026-10-12');
        $this->holdRoom('Z504', '2026-10-10', '2026-10-12');
    }

    private function holdRoom(string $roomNumber, string $arrival, string $departure): void
    {
        $booking = Booking::create([
            'created_by' => 'row452', 'booking_date' => '2026-10-01', 'booking_name' => "Hold {$roomNumber}", 'company_id' => $this->company->id,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'num_of_days' => 2,
            'registration_status_id' => $this->status->booking_status_id, 'market_id' => $this->market->id, 'customer_source_id' => $this->source->id,
        ]);
        BookingRoom::create([
            'booking_id' => $booking->id, 'room_class_id' => $this->roomClass->id, 'room_number' => $roomNumber,
            'arrival_date' => $arrival, 'departure_date' => $departure, 'status' => BookingRoom::STATUS_BOOKED,
        ]);
    }

    private function create(int $quantity)
    {
        $rooms = array_fill(0, $quantity, ['roomClassId' => $this->roomClass->id, 'arrivalDate' => '2026-10-07', 'departureDate' => '2026-10-12']);

        return $this->postJson('/api/bookings', [
            'booking_name' => 'BK 452', 'company_id' => $this->company->id,
            'arrival_date' => '2026-10-07', 'departure_date' => '2026-10-12', 'num_of_days' => 5,
            'registration_status_id' => $this->status->booking_status_id, 'market_id' => $this->market->id, 'customer_source_id' => $this->source->id,
            'room_allocations' => [['roomClassId' => $this->roomClass->id, 'quantity' => $quantity, 'rooms' => $rooms]],
        ]);
    }

    public function test_can_take_as_many_rooms_as_the_lowest_nightly_availability_even_without_continuous_free_rooms(): void
    {
        $this->create(3)->assertSuccessful();

        $this->assertSame(3, BookingRoom::where('room_class_id', $this->roomClass->id)->whereNull('room_number')->count());
    }

    public function test_cannot_take_more_rooms_than_the_lowest_nightly_availability(): void
    {
        $response = $this->create(4);

        $response->assertStatus(422);
        $this->assertStringContainsString('Số phòng trống hiện tại: 3', $response->json('message') ?? '');
    }
}
