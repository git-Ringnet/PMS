<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\Market;
use App\Models\Payment;
use App\Models\PaymentMethod;
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
 * Task 473 (TC_LT_22 dòng 297 "Check lại option add"): tạo đăng ký kèm cọc (deposit_details)
 * phải tuân theo quyền thêm cọc fo.deposit.create, không chỉ fo.booking.create.
 */
class Row473BookingDepositPermissionTest extends TestCase
{
    use RefreshDatabase;

    private RoomClass $roomClass;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['SystemConfigurationSeeder', 'DepartmentSeeder', 'HotelDefinitionSeeder', 'SpecialRequestSeeder', 'SystemDateRollSeeder', 'SystemDefinitionSeeder', 'BookingStatusSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => $seeder]);
        }
        PaymentMethod::firstOrCreate(['code' => 'CA'], ['name' => 'Cash', 'payment_group' => 1]);
        Market::first() ?? Market::create(['name' => 'Market 1', 'code' => 'M1']);
        CustomerSource::first() ?? CustomerSource::create(['name' => 'Source 1', 'code' => 'S1']);
        Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);
        $this->roomClass = RoomClass::create(['name' => 'Class 473', 'code' => 'C473', 'is_active' => 1]);
        $form = RoomForm::first() ?? RoomForm::create(['name' => 'King', 'code' => 'K']);
        Room::create(['room_number' => 'R473', 'room_class_id' => $this->roomClass->id, 'room_form_id' => $form->id, 'floor' => 1, 'is_active' => 1, 'status' => 'clean']);
    }

    private function userWith(array $codes): User
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'r473_' . uniqid(), 'name' => 'r473', 'level' => 3, 'is_active' => true]);
        foreach ($codes as $code) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO'])->id]);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'booking_name' => 'BK cọc 473',
            'company_id' => Company::first()->id,
            'arrival_date' => '2026-10-10',
            'departure_date' => '2026-10-12',
            'num_of_days' => 2,
            'registration_status_id' => RegistrationStatus::where('booking_status_id', 20)->firstOrFail()->booking_status_id,
            'market_id' => Market::first()->id,
            'customer_source_id' => CustomerSource::first()->id,
            'room_allocations' => [[
                'roomClassId' => $this->roomClass->id, 'quantity' => 1,
                'rooms' => [['roomClassId' => $this->roomClass->id, 'arrivalDate' => '2026-10-10', 'departureDate' => '2026-10-12']],
            ]],
        ], $extra);
    }

    private function deposits(): array
    {
        return ['deposit_details' => [['amount' => 500000, 'paymentMethodId' => 'CA', 'note' => 'Đặt cọc', 'date' => '2026-10-09']]];
    }

    public function test_create_booking_with_deposit_is_rejected_without_deposit_create_permission(): void
    {
        $user = $this->userWith(['fo.booking.create', 'fo.booking.view']);

        $this->actingAs($user)->postJson('/api/bookings', $this->payload($this->deposits()))
            ->assertStatus(403);

        $this->assertSame(0, Payment::count(), 'Không được tạo dòng cọc khi thiếu quyền thêm cọc');
        $this->assertSame(0, Booking::count(), 'Không tạo booking nửa chừng khi bị từ chối');
    }

    public function test_create_booking_with_deposit_works_with_deposit_create_permission(): void
    {
        $user = $this->userWith(['fo.booking.create', 'fo.deposit.create']);

        $this->actingAs($user)->postJson('/api/bookings', $this->payload($this->deposits()))->assertSuccessful();

        $this->assertSame(1, Payment::where('pack2', Payment::PACK2_DEPOSIT)->count());
    }

    public function test_create_booking_without_deposit_still_works_without_deposit_permission(): void
    {
        $user = $this->userWith(['fo.booking.create']);

        $this->actingAs($user)->postJson('/api/bookings', $this->payload())->assertSuccessful();

        $this->assertSame(0, Payment::count());
        $this->assertSame(1, Booking::count());
    }

    public function test_empty_or_zero_deposit_rows_do_not_need_deposit_permission(): void
    {
        $user = $this->userWith(['fo.booking.create']);

        $this->actingAs($user)->postJson('/api/bookings', $this->payload(['deposit_details' => [['amount' => 0, 'paymentMethodId' => 'CA']]]))
            ->assertSuccessful();

        $this->assertSame(0, Payment::count());
    }
}
