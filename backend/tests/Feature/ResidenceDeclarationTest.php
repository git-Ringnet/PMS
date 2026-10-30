<?php

namespace Tests\Feature;

use App\Models\HotelSetting;
use App\Models\IdType;
use App\Models\ResidenceDeclaration;
use App\Models\User;
use Database\Seeders\GuestDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidenceDeclarationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_marks_guests_declared_per_stay_and_is_idempotent(): void
    {
        $user = User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create(['username' => 'frontdesk01']);

        $payload = [
            'guests' => [
                ['guest_id' => 'G001', 'guest_kind' => 'guest', 'booking_id' => 10, 'booking_room_id' => 'BR01'],
                ['guest_id' => 'C001', 'guest_kind' => 'child', 'booking_id' => 10, 'booking_room_id' => 'BR01'],
                ['guest_id' => 'G001', 'guest_kind' => 'guest', 'booking_id' => 10, 'booking_room_id' => 'BR01'],
            ],
        ];

        $this->actingAs($user)->postJson('/api/residence-declarations', $payload)
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        // Xuất lại lần nữa không tạo bản ghi trùng
        $this->actingAs($user)->postJson('/api/residence-declarations', $payload)->assertOk();
        $this->assertSame(2, ResidenceDeclaration::count());

        // Cùng khách nhưng lượt ở khác (phòng thuê khác) được ghi nhận riêng
        $this->actingAs($user)->postJson('/api/residence-declarations', [
            'guests' => [['guest_id' => 'G001', 'guest_kind' => 'guest', 'booking_id' => 11, 'booking_room_id' => 'BR02']],
        ])->assertOk();
        $this->assertSame(3, ResidenceDeclaration::count());

        $this->assertDatabaseHas('residence_declarations', [
            'guest_id' => 'G001',
            'guest_kind' => 'guest',
            'booking_room_id' => 'BR01',
            'declarer' => 'frontdesk01',
            'status' => 1,
        ]);
    }

    public function test_index_filters_by_booking_room_ids(): void
    {
        $user = User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create();

        $this->actingAs($user)->postJson('/api/residence-declarations', [
            'guests' => [
                ['guest_id' => 'G001', 'guest_kind' => 'guest', 'booking_room_id' => 'BR01'],
                ['guest_id' => 'G002', 'guest_kind' => 'guest', 'booking_room_id' => 'BR02'],
            ],
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/api/residence-declarations?booking_room_ids[]=BR02');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('G002', $response->json('data.0.guest_id'));
    }

    public function test_store_validates_payload(): void
    {
        $user = User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create();

        $this->actingAs($user)->postJson('/api/residence-declarations', [
            'guests' => [['guest_id' => 'G001', 'guest_kind' => 'other']],
        ])->assertStatus(422);
    }

    public function test_hotel_settings_exposes_immigration_export_config(): void
    {
        $user = User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create();
        HotelSetting::create(['hotel_name' => 'Test Hotel']);

        $this->actingAs($user)->getJson('/api/hotel-settings')
            ->assertOk()
            ->assertJsonPath('data.Immigration_ExportExelChangeToRegistered', '0');

        DB::table('hotel_configs')
            ->where('name', 'Immigration_ExportExelChangeToRegistered')
            ->update(['value' => '1']);

        $this->actingAs($user)->getJson('/api/hotel-settings')
            ->assertJsonPath('data.Immigration_ExportExelChangeToRegistered', '1');
    }

    public function test_id_types_have_police_names(): void
    {
        $this->seed(GuestDefinitionSeeder::class);

        $this->assertSame('1 - Thẻ CCCD', IdType::where('code', 'CCCD')->value('name_according_police'));
        $this->assertSame('4 - Hộ chiếu', IdType::where('code', 'Passport')->value('name_according_police'));
        $this->assertSame('3 - Giấy phép lái xe', IdType::where('code', 'GPLX')->value('name_according_police'));
        $this->assertSame('9 - Giấy Tờ Khác', IdType::where('code', 'Other')->value('name_according_police'));
    }
}
