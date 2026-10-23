<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\HousekeepingServiceBill;
use App\Models\HousekeepingServiceBillDetail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoomClass;
use App\Models\ServiceBill;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HousekeepingBillReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private BookingRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        $this->user = User::factory()->create();
        $role = Role::create(['code' => 'hk_reversal_test', 'name' => 'HK reversal', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        foreach (['hk.service.bill', 'hk.service.delete', 'fo.service.add', 'fo.service.edit', 'fo.service.delete', 'fo.service.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        HotelConfig::updateOrCreate(['name' => 'RuleUserCorrectOrPostBillPaymentOldDay'], ['value' => 'hk_reversal_test']);
        SystemDateRoll::create(['system_date' => '2026-08-11', 'actual_date' => now(), 'shift' => '1', 'username' => $this->user->username]);
        $booking = Booking::create([
            'booking_name' => 'Reversal test', 'arrival_date' => '2026-08-11', 'departure_date' => '2026-08-12',
            'num_of_days' => 1, 'booking_date' => '2026-08-11', 'created_by' => $this->user->username,
        ]);
        $class = RoomClass::firstOrCreate(['code' => 'TEST'], ['name' => 'Test']);
        $this->room = BookingRoom::create([
            'id' => 'HK-202', 'booking_id' => $booking->id, 'room_class_id' => $class->id,
            'arrival_date' => '2026-08-11', 'departure_date' => '2026-08-12', 'rate' => 0,
        ]);
    }

    private function bill(string $department = 'HK'): HousekeepingServiceBill
    {
        $service = ServiceBill::create([
            'Date' => '2026-08-11', 'OpenTime' => '10:50', 'DepartmentId' => $department, 'ServiceId' => 'MB',
            'Guest' => 'Test', 'DescriptionServive' => 'Minibar', 'Username' => $this->user->username,
            'Amount' => 230000, 'Quantity' => 1, 'Status' => 1, 'Edit' => 0,
            'RegisterID2' => $this->room->booking_id, 'RentalRoomId2' => $this->room->id,
        ]);
        $bill = HousekeepingServiceBill::create([
            'BookingId' => $this->room->booking_id, 'Date' => '2026-08-11', 'Department' => $department,
            'BillServiceId' => $service->Ma, 'BillOriginalAmount' => 235000, 'BillAmount' => 230000,
            'BillDiscountAmount' => 10000, 'BillEdit' => 0, 'Status' => 1, 'Outlet' => 'MB',
        ]);
        foreach ([[1, 'Coca', 100000, 2, 200000], [2, 'Water', 30000, 1, 30000]] as [$id, $name, $rate, $qty, $amount]) {
            HousekeepingServiceBillDetail::create([
                'BillId' => $bill->Ma, 'DetailId' => $id, 'Product' => $name, 'Rate' => $rate,
                'Quantity' => $qty, 'TotalAmount' => $amount, 'DiscountAmount' => $id === 1 ? 10000 : 0,
                'IncreaseAmount' => $id === 1 ? 5000 : 0, 'Deleted' => 0,
            ]);
        }
        return $bill;
    }

    private function cancelInHk(HousekeepingServiceBill $bill)
    {
        return $this->actingAs($this->user)->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", ['reason' => 'Wrong posting']);
    }

    private function assertReversed(HousekeepingServiceBill $original): void
    {
        $original->refresh();
        $positive = ServiceBill::findOrFail($original->BillServiceId);
        $negative = HousekeepingServiceBill::where('BillServiceId', $positive->Pack1)->sole();
        $this->assertSame(1, (int) $original->BillEdit);
        $this->assertSame(1, (int) $negative->BillEdit);
        $this->assertEquals(0, (float) $original->BillAmount + (float) $negative->BillAmount);
        $this->assertEquals(0, (float) $original->BillOriginalAmount + (float) $negative->BillOriginalAmount);
        $this->assertEquals(0, (float) $original->BillDiscountAmount + (float) $negative->BillDiscountAmount);
        $positiveDetails = HousekeepingServiceBillDetail::where('BillId', $original->Ma)->orderBy('DetailId')->get();
        $negativeDetails = HousekeepingServiceBillDetail::where('BillId', $negative->Ma)->orderBy('DetailId')->get();
        $this->assertCount(2, $negativeDetails);
        foreach ($positiveDetails as $index => $detail) {
            $reversal = $negativeDetails[$index];
            $this->assertSame($detail->Product, $reversal->Product);
            $this->assertEquals($detail->Rate, $reversal->Rate);
            foreach (['Quantity', 'TotalAmount', 'DiscountAmount', 'IncreaseAmount'] as $field) {
                $this->assertEquals(0, (float) $detail->$field + (float) $reversal->$field);
            }
            $this->assertSame(1, (int) $detail->Deleted);
            $this->assertSame(1, (int) $reversal->Deleted);
        }
        $rows = $this->actingAs($this->user)->getJson('/api/housekeeping/service-bills?status=deleted')->assertOk()->json('data');
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertCount(2, $row['products']);
            $this->assertFalse($row['can_cancel']);
            $sign = $row['amount'] < 0 ? -1 : 1;
            $this->assertEquals($sign * 2, $row['products'][0]['quantity']);
            $this->assertEquals($sign * 200000, $row['products'][0]['amount']);
        }
    }

    public function test_hk_cancellation_reverses_every_product_and_keeps_deleted_details_readable(): void
    {
        $bill = $this->bill();
        $this->cancelInHk($bill)->assertOk();
        $this->assertReversed($bill);
        $this->cancelInHk($bill)->assertStatus(422);
        $this->assertSame(2, HousekeepingServiceBill::count());
        $this->assertSame(4, HousekeepingServiceBillDetail::count());
    }

    public function test_fo_cannot_cancel_hk_bill_in_room_even_when_hk_can_cancel_fo(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SearchHouseKeeping_AllowHKCorrectBillFO'], ['value' => '1']);
        $bill = $this->bill();
        $this->actingAs($this->user)->postJson("/api/booking-rooms/{$this->room->id}/services/cancel", [
            'service_bill_ids' => [$bill->BillServiceId], 'reason' => 'Wrong posting',
        ])->assertStatus(422)->assertJsonPath('message', 'Vui lòng liên hệ bộ phận HK để xóa bill.');
        $this->assertSame(0, (int) $bill->fresh()->BillEdit);
        $this->assertSame(1, ServiceBill::count());
    }

    public function test_fo_cannot_cancel_hk_bill_transferred_to_master(): void
    {
        $bill = $this->bill();
        ServiceBill::whereKey($bill->BillServiceId)->update(['RentalRoomId2' => null]);
        $this->actingAs($this->user)->postJson("/api/booking-rooms/master-{$this->room->booking_id}/services/cancel", [
            'service_bill_ids' => [$bill->BillServiceId], 'reason' => 'Wrong posting',
        ])->assertStatus(422)->assertJsonPath('message', 'Vui lòng liên hệ bộ phận HK để xóa bill.');
        $this->assertSame(1, ServiceBill::count());
    }

    public function test_fo_can_cancel_its_own_hk_product_bill_and_creates_hk_reversal_rows(): void
    {
        $bill = $this->bill('FO');
        $this->actingAs($this->user)->postJson("/api/booking-rooms/{$this->room->id}/services/cancel", [
            'service_bill_ids' => [$bill->BillServiceId], 'reason' => 'Wrong posting',
        ])->assertOk();
        $this->assertReversed($bill);
    }

    public function test_hk_rejects_paid_bill_without_creating_reversal(): void
    {
        $bill = $this->bill();
        ServiceBill::whereKey($bill->BillServiceId)->update(['PaymentId' => 'PAID']);
        $this->cancelInHk($bill)->assertStatus(422);
        $this->assertSame(1, HousekeepingServiceBill::count());
        $this->assertSame(2, HousekeepingServiceBillDetail::count());
    }

    public function test_fo_master_cancellation_reverses_its_own_product_bill(): void
    {
        $bill = $this->bill('FO');
        ServiceBill::whereKey($bill->BillServiceId)->update(['RentalRoomId2' => null]);
        $this->actingAs($this->user)->postJson("/api/booking-rooms/master-{$this->room->booking_id}/services/cancel", [
            'service_bill_ids' => [$bill->BillServiceId], 'reason' => 'Wrong posting',
        ])->assertOk();
        $this->assertReversed($bill);
    }

    public function test_hk_rejects_vat_bill_without_creating_reversal(): void
    {
        $bill = $this->bill();
        ServiceBill::whereKey($bill->BillServiceId)->update(['VatId' => 202]);
        $this->cancelInHk($bill)->assertStatus(422);
        $this->assertSame(1, ServiceBill::count());
        $this->assertSame(0, (int) $bill->fresh()->BillEdit);
    }

    public function test_hk_cancellation_requires_a_reason(): void
    {
        $bill = $this->bill();
        $this->actingAs($this->user)->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", [])->assertStatus(422);
        $this->assertSame(1, ServiceBill::count());
    }
}
