<?php

namespace Tests\Feature;

use App\Models\RoomRateCode;
use App\Models\RoomRateDailyMapping;
use App\Models\RoomRatePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomRateDailyMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_updates_only_selected_dates_and_can_remove_one_date(): void
    {
        $user = User::factory()->withRole('branch_admin')->create();
        $this->createRateCode();
        RoomRateDailyMapping::create(['RateCode' => 'TEST', 'Date' => '2026-08-09', 'Code' => 'A']);

        $this->actingAs($user)->postJson('/api/room-rate-codes/TEST/daily-mappings', [
            'mode' => 'merge',
            'mappings' => [
                ['Date' => '2026-08-09', 'Code' => 'B'],
                ['Date' => '2026-08-10', 'Code' => 'A'],
            ],
        ])->assertSuccessful()
            ->assertJsonPath('data.0.Date', '2026-08-09')
            ->assertJsonPath('data.0.Code', 'B')
            ->assertJsonPath('data.1.Code', 'A');

        $this->assertDatabaseHas('room_rate_daily_mappings', ['RateCode' => 'TEST', 'Date' => '2026-08-09', 'Code' => 'B']);
        $this->assertDatabaseHas('room_rate_daily_mappings', ['RateCode' => 'TEST', 'Date' => '2026-08-10', 'Code' => 'A']);

        $this->actingAs($user)->postJson('/api/room-rate-codes/TEST/daily-mappings', [
            'mode' => 'merge',
            'mappings' => [],
            'delete_dates' => ['2026-08-09'],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('room_rate_daily_mappings', ['RateCode' => 'TEST', 'Date' => '2026-08-09']);
        $this->assertDatabaseHas('room_rate_daily_mappings', ['RateCode' => 'TEST', 'Date' => '2026-08-10', 'Code' => 'A']);
    }

    public function test_unknown_plan_is_rejected_without_deleting_existing_mappings(): void
    {
        $user = User::factory()->withRole('branch_admin')->create();
        $this->createRateCode();
        RoomRateDailyMapping::create(['RateCode' => 'TEST', 'Date' => '2026-08-09', 'Code' => 'A']);

        $this->actingAs($user)->postJson('/api/room-rate-codes/TEST/daily-mappings', [
            'mode' => 'replace',
            'mappings' => [['Date' => '2026-08-10', 'Code' => 'UNKNOWN']],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('room_rate_daily_mappings', ['RateCode' => 'TEST', 'Date' => '2026-08-09', 'Code' => 'A']);
    }

    private function createRateCode(): void
    {
        RoomRateCode::create(['Ma' => 'TEST', 'IsDaily' => true]);
        RoomRatePlan::create(['RateCode' => 'TEST', 'Code' => 'A', 'Period' => []]);
        RoomRatePlan::create(['RateCode' => 'TEST', 'Code' => 'B', 'Period' => []]);
    }

    /**
     * Mã giá phòng đã có booking dùng: vẫn cho cập nhật giá (thông tin mã giá, bảng giá, lịch giá theo ngày);
     * xóa mã giá / xóa loại giá vẫn bị chặn để giữ lịch sử.
     */
    public function test_used_rate_code_can_still_be_updated_but_not_deleted(): void
    {
        $user = User::factory()->withRole('branch_admin')->create();
        $this->createRateCode();
        \Illuminate\Support\Facades\DB::table('booking_statuses')->insertOrIgnore(['id' => 0, 'name' => 'Booking']);
        \Illuminate\Support\Facades\DB::table('booking_rooms')->insert([
            'id' => 'BR-RATE-1', 'rate_code' => 'TEST', 'rate' => 500000, 'arrival_date' => '2026-08-09', 'departure_date' => '2026-08-10',
        ]);

        $this->actingAs($user)->putJson('/api/room-rate-codes/TEST', ['Description' => 'Giá mới'])->assertSuccessful();
        $this->actingAs($user)->postJson('/api/room-rate-codes/TEST/plans', [
            'Code' => 'A', 'Period' => ['A_STD_Double' => 650000],
        ])->assertSuccessful();
        $this->actingAs($user)->postJson('/api/room-rate-codes/TEST/daily-mappings', [
            'mode' => 'merge', 'mappings' => [['Date' => '2026-08-12', 'Code' => 'A']],
        ])->assertSuccessful();

        $this->assertSame('Giá mới', RoomRateCode::find('TEST')->Description);
        $this->assertEquals(['A_STD_Double' => 650000], RoomRatePlan::where('RateCode', 'TEST')->where('Code', 'A')->first()->Period);
        // Booking đã tạo giữ nguyên mã giá và giá cũ.
        $this->assertDatabaseHas('booking_rooms', ['id' => 'BR-RATE-1', 'rate_code' => 'TEST', 'rate' => 500000]);

        // Không đổi được mã (khóa) của mã giá, không xóa được mã giá / loại giá đang dùng.
        $this->actingAs($user)->putJson('/api/room-rate-codes/TEST', ['Ma' => 'TEST2'])->assertStatus(422);
        $this->actingAs($user)->deleteJson('/api/room-rate-codes/TEST')->assertStatus(422);
        $this->actingAs($user)->deleteJson('/api/room-rate-codes/TEST/plans/A')->assertStatus(422);
        $this->assertDatabaseHas('room_rate_codes', ['Ma' => 'TEST']);
    }
}
