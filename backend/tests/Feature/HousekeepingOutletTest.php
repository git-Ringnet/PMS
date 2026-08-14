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
            'default_service_charge_percent' => 5,
            'default_tax_percent' => 8,
            'default_special_tax_percent' => 2,
            'order_index' => 10,
        ])->assertCreated()
            ->assertJsonPath('show_in_add_service', false)
            ->assertJsonPath('default_service_charge_percent', 5)
            ->assertJsonPath('default_tax_percent', 8)
            ->assertJsonPath('default_special_tax_percent', 2);

        $outletId = $response->json('id');

        $this->actingAs($user)->putJson("/api/housekeeping/outlets/{$outletId}", [
            'code' => 'TS',
            'name' => 'Test Service',
            'service_code' => 'TS',
            'is_active' => false,
            'show_in_add_service' => true,
            'default_service_charge_percent' => 6,
            'default_tax_percent' => 10,
            'default_special_tax_percent' => 3,
            'order_index' => 10,
        ])->assertSuccessful()
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('show_in_add_service', true)
            ->assertJsonPath('default_service_charge_percent', 6)
            ->assertJsonPath('default_tax_percent', 10)
            ->assertJsonPath('default_special_tax_percent', 3);

        $this->assertDatabaseHas('housekeeping_outlets', [
            'id' => $outletId,
            'is_active' => false,
            'show_in_add_service' => true,
            'default_service_charge_percent' => 6,
            'default_tax_percent' => 10,
            'default_special_tax_percent' => 3,
        ]);
    }
}
