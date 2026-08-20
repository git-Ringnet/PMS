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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
}
