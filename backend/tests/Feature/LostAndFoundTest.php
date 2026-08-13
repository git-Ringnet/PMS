<?php

namespace Tests\Feature;

use App\Models\LostAndFoundItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LostAndFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_a_lost_and_found_record(): void
    {
        $user = User::factory()->create(['username' => 'hk_test']);

        $response = $this->actingAs($user)->postJson('/api/lost-and-found', [
            'status' => 'found',
            'guest_info' => 'Khách phòng 106',
            'item_found' => 'Điện thoại',
            'date_reported' => '2026-07-16',
            'date_found' => '2026-07-16',
            'who_found' => 'Nhân viên HK',
            'where_found' => 'Phòng 106',
            'storage_location' => 'Tủ FO số 1',
            'image' => ['data:image/png;base64,aGVsbG8='],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('created_by', 'hk_test')
            ->assertJsonPath('date_found', '2026-07-16')
            ->assertJsonPath('image.0', 'data:image/png;base64,aGVsbG8=');

        $id = $response->json('id');

        $this->actingAs($user)->putJson("/api/lost-and-found/{$id}", [
            'status' => 'found',
            'item_found' => 'Điện thoại',
            'date_reported' => '2026-07-16',
            'date_handling' => '2026-07-17',
            'method_handling' => 'Giao trực tiếp',
            'delieved_handling' => 'Lễ tân',
            'received_handling' => 'Khách phòng 106',
            'image' => [],
        ])->assertOk()
            ->assertJsonPath('date_handling', '2026-07-17')
            ->assertJsonPath('method_handling', 'Giao trực tiếp')
            ->assertJsonPath('created_by', 'hk_test');
    }

    public function test_it_filters_by_status_report_date_and_search_text(): void
    {
        $user = User::factory()->create();

        LostAndFoundItem::create([
            'log_no' => 1,
            'status' => 'lost',
            'guest_info' => 'Khách phòng 201',
            'item_found' => 'Ví da',
            'date_reported' => '2026-07-15',
        ]);
        LostAndFoundItem::create([
            'log_no' => 2,
            'status' => 'found',
            'guest_info' => 'Khách phòng 106',
            'item_found' => 'Điện thoại',
            'date_reported' => '2026-07-16',
        ]);

        $this->actingAs($user)
            ->getJson('/api/lost-and-found?status=found&from=2026-07-16&to=2026-07-16&search=106')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.item_found', 'Điện thoại');
    }

    public function test_it_validates_status_and_required_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/lost-and-found', [
            'status' => 'closed',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['item_found', 'status']);
    }
}
