<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BookingRoomServiceController;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\HotelConfig;
use App\Models\HousekeepingServiceBill;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RoomClass;
use App\Models\Shift;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dòng 341: thông số SearchHouseKeeping_AllowHKCorrectBillFO cho phép Buồng phòng hủy bill do Lễ tân post.
 */
class HousekeepingCancelFoBillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private BookingRoom $room;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
        Shift::create(['name' => '1', 'start_time' => '06:00:00', 'end_time' => '14:00:00']);
        Shift::create(['name' => '2', 'start_time' => '14:00:00', 'end_time' => '22:00:00']);
        Shift::create(['name' => '3', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);

        $this->user = User::factory()->create();
        $role = \App\Models\Role::create(['code' => 'hk_cancel_test', 'name' => 'HK cancel', 'level' => 3, 'department_scope' => 'HK', 'is_active' => true]);
        foreach (['hk.service.bill', 'hk.service.delete', 'fo.service.add', 'fo.service.edit', 'fo.service.view'] as $code) {
            $permission = \App\Models\Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'HK']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->user->roles()->attach($role->id);
        HotelConfig::updateOrCreate(['name' => 'RuleUserCorrectOrPostBillPaymentOldDay'], ['value' => 'hk_cancel_test']);

        SystemDateRoll::create(['system_date' => '2026-08-06', 'actual_date' => now(), 'shift' => '1', 'username' => $this->user->username]);
        $booking = Booking::create([
            'booking_name' => 'GAL HK', 'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07',
            'num_of_days' => 1, 'booking_date' => '2026-08-06', 'created_by' => $this->user->username,
        ]);
        $roomClass = RoomClass::firstOrCreate(['code' => 'TEST'], ['name' => 'Test Room']);
        $this->room = BookingRoom::create([
            'id' => 'GHK-1', 'booking_id' => $booking->id, 'room_class_id' => $roomClass->id,
            'arrival_date' => '2026-08-06', 'departure_date' => '2026-08-07', 'rate' => 0,
        ]);
        $category = ProductCategory::create(['name' => 'Minibar', 'outlet' => 'MB']);
        $this->product = Product::create([
            'product_category_id' => $category->id, 'name' => 'Coca Cola', 'price' => 23000, 'open_key' => true,
        ]);
    }

    /** Post một bill minibar theo bộ phận (FO = Lễ tân post, HK = Buồng phòng post). */
    private function postBill(string $source): HousekeepingServiceBill
    {
        $this->actingAs($this->user)->postJson('/api/booking-room-services/post-housekeeping-bill', [
            'booking_room_id' => $this->room->id,
            'posting_source' => $source,
            'service_date' => '2026-08-06',
            'folio' => 1,
            'bills' => [[
                'group' => 'MB',
                'items' => [[
                    'id' => $this->product->id, 'name' => $this->product->name, 'qty' => 1,
                    'price' => 23000, 'original_rate' => 23000, 'net_price' => 23000, 'total_amount' => 23000,
                    'service_charge' => 0, 'special_tax' => 0, 'tax' => 0,
                ]],
            ]],
        ])->assertSuccessful();

        return HousekeepingServiceBill::latest('Ma')->firstOrFail();
    }

    private function searchRow(HousekeepingServiceBill $bill): array
    {
        $rows = $this->actingAs($this->user)
            ->getJson('/api/housekeeping/service-bills?date_from=2026-08-01&date_to=2026-08-31&status=all')
            ->assertOk()->json('data');

        return collect($rows)->firstWhere('id', $bill->Ma);
    }

    public function test_fo_bill_shows_cancel_button_but_is_blocked_when_setting_is_zero(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SearchHouseKeeping_AllowHKCorrectBillFO'], ['value' => '0']);
        $bill = $this->postBill('FO');
        $this->assertSame('FO', $bill->Department);

        $row = $this->searchRow($bill);
        $this->assertTrue($row['can_cancel']);          // vẫn hiển thị nút hủy
        $this->assertTrue($row['fo_cancel_blocked']);

        $this->actingAs($this->user)
            ->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", ['reason' => 'post nhầm'])
            ->assertStatus(422)
            ->assertJsonPath('message', BookingRoomServiceController::FO_BILL_CANCEL_BLOCKED_MESSAGE);

        $this->assertSame(0, (int) $bill->fresh()->BillEdit);
    }

    public function test_fo_bill_can_be_cancelled_when_setting_is_one(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SearchHouseKeeping_AllowHKCorrectBillFO'], ['value' => '1']);
        $bill = $this->postBill('FO');

        $this->assertFalse($this->searchRow($bill)['fo_cancel_blocked']);

        $this->actingAs($this->user)
            ->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", ['reason' => 'post nhầm'])
            ->assertOk();

        $this->assertTrue((bool) $bill->fresh()->BillEdit);
    }

    public function test_hk_bill_cancel_is_not_affected_by_setting(): void
    {
        HotelConfig::updateOrCreate(['name' => 'SearchHouseKeeping_AllowHKCorrectBillFO'], ['value' => '0']);
        $bill = $this->postBill('HK');

        $row = $this->searchRow($bill);
        $this->assertTrue($row['can_cancel']);
        $this->assertFalse($row['fo_cancel_blocked']);

        $this->actingAs($this->user)
            ->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", ['reason' => 'post nhầm'])
            ->assertOk();
    }

    public function test_missing_setting_defaults_to_blocked(): void
    {
        HotelConfig::where('name', 'SearchHouseKeeping_AllowHKCorrectBillFO')->delete();
        $bill = $this->postBill('FO');

        $this->actingAs($this->user)
            ->postJson("/api/housekeeping/service-bills/{$bill->Ma}/cancel", ['reason' => 'post nhầm'])
            ->assertStatus(422);
    }
}
