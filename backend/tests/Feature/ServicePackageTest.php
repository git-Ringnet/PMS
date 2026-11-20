<?php

namespace Tests\Feature;

use App\Models\HotelService;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_crud_requires_catalog_service_and_returns_details(): void
    {
        $user = User::factory()->withRole('branch_admin')->create();
        HotelService::create(['code' => 'PK', 'name' => 'Dịch vụ gói']);

        $payload = [
            'code' => 'HOLIDAY',
            'description' => 'Gói nghỉ lễ',
            'currency' => 'VND',
            'nights' => 2,
            'display_price' => 500000,
            'begin_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'days_of_week' => [0, 6],
            'items' => [[
                'service_code' => 'PK',
                'quantity' => 1,
                'rate' => 500000,
                'order_day' => 1,
            ]],
        ];

        $this->actingAs($user)->postJson('/api/service-packages', $payload)
            ->assertCreated()
            ->assertJsonPath('data.code', 'HOLIDAY')
            ->assertJsonPath('data.items.0.service_code', 'PK');

        $package = ServicePackage::where('code', 'HOLIDAY')->firstOrFail();
        $this->assertDatabaseHas('service_package_items', [
            'service_package_id' => $package->id,
            'detail_no' => 1,
            'service_code' => 'PK',
        ]);

        $this->actingAs($user)->putJson("/api/service-packages/{$package->id}", [
            ...$payload,
            'description' => 'Gói nghỉ lễ đã sửa',
        ])->assertSuccessful()
            ->assertJsonPath('data.description', 'Gói nghỉ lễ đã sửa');
    }
}
