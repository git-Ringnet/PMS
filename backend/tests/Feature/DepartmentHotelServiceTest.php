<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HotelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentHotelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_service_uses_department_foreign_key_and_returns_department_details(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['code' => 'FO', 'name' => 'Reception/ Lê Tân']);

        $this->actingAs($user)->postJson('/api/hotel-services', [
            'code' => 'TS',
            'name' => 'Test Service',
            'department_id' => $department->id,
        ])->assertCreated()
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.department', 'FO')
            ->assertJsonPath('data.department_name', 'Reception/ Lê Tân');

        $service = HotelService::where('code', 'TS')->firstOrFail();
        $this->assertDatabaseHas('department_hotel_service', [
            'department_id' => $department->id,
            'hotel_service_id' => $service->id,
        ]);
    }

    public function test_department_service_link_can_be_added_updated_and_removed(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['code' => 'HK', 'name' => 'House Keeping/Buồng Phòng']);
        $service = HotelService::create([
            'code' => 'MB',
            'name' => 'Minibar',
            'department_id' => $department->id,
        ]);

        $this->actingAs($user)->postJson("/api/departments/{$department->id}/services", [
            'hotel_service_id' => $service->id,
            'description' => 'Minibar phòng',
        ])->assertSuccessful();

        $this->actingAs($user)->putJson("/api/departments/{$department->id}/services/{$service->id}", [
            'description' => 'Minibar cập nhật',
        ])->assertSuccessful();

        $this->assertDatabaseHas('department_hotel_service', [
            'department_id' => $department->id,
            'hotel_service_id' => $service->id,
            'description' => 'Minibar cập nhật',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/departments/{$department->id}/services/{$service->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('department_hotel_service', [
            'department_id' => $department->id,
            'hotel_service_id' => $service->id,
        ]);
    }

    public function test_fo_service_list_uses_department_service_links(): void
    {
        $user = User::factory()->create();
        $frontOffice = Department::create(['code' => 'FO', 'name' => 'Front Office']);
        $housekeeping = Department::create(['code' => 'HK', 'name' => 'Housekeeping']);

        $frontOfficeService = HotelService::create([
            'code' => 'FO-SVC',
            'name' => 'Front Office Service',
            'department_id' => $housekeeping->id,
        ]);
        $housekeepingService = HotelService::create([
            'code' => 'HK-SVC',
            'name' => 'Housekeeping Service',
            'department_id' => $housekeeping->id,
        ]);

        $frontOffice->hotelServices()->attach($frontOfficeService->id);
        $housekeeping->hotelServices()->attach($housekeepingService->id);

        $response = $this->actingAs($user)->getJson('/api/booking-services/fo-list');

        $response->assertSuccessful()
            ->assertJsonPath('data.0.code', 'FO-SVC')
            ->assertJsonMissing(['code' => 'HK-SVC']);
    }
}
