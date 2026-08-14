<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HousekeepingOutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_service_visibility_flag_can_be_created_and_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/housekeeping/outlets', [
            'code' => 'TS',
            'name' => 'Test Service',
            'service_code' => 'TS',
            'is_active' => true,
            'show_in_add_service' => false,
            'order_index' => 10,
        ])->assertCreated()
            ->assertJsonPath('show_in_add_service', false);

        $outletId = $response->json('id');

        $this->actingAs($user)->putJson("/api/housekeeping/outlets/{$outletId}", [
            'code' => 'TS',
            'name' => 'Test Service',
            'service_code' => 'TS',
            'is_active' => false,
            'show_in_add_service' => true,
            'order_index' => 10,
        ])->assertSuccessful()
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('show_in_add_service', true);

        $this->assertDatabaseHas('housekeeping_outlets', [
            'id' => $outletId,
            'is_active' => false,
            'show_in_add_service' => true,
        ]);
    }
}
