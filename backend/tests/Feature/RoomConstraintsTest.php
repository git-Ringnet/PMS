<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\StandardRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        
        \Laravel\Sanctum\Sanctum::actingAs($user);
    }

    /**
     * Test that we cannot deactivate the last active Room Class.
     */
    public function test_cannot_deactivate_last_active_room_class()
    {
        // Setup: Create exactly one active room class
        $roomClass = RoomClass::create([
            'name' => 'Superior Double',
            'code' => 'SUPD',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/room-classes/{$roomClass->id}", [
            'name' => $roomClass->name,
            'code' => $roomClass->code,
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể tắt trạng thái hoạt động vì phải có ít nhất 1 loại phòng đang hoạt động!'
        ]);
    }

    /**
     * Test that we can deactivate a Room Class if there are other active ones,
     * provided it has no rates or rooms.
     */
    public function test_can_deactivate_room_class_if_not_last_and_not_used()
    {
        $roomClass1 = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);
        $roomClass2 = RoomClass::create(['name' => 'Class 2', 'code' => 'C2', 'is_active' => true]);

        $response = $this->putJson("/api/room-classes/{$roomClass1->id}", [
            'name' => $roomClass1->name,
            'code' => $roomClass1->code,
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse((bool) $roomClass1->fresh()->is_active);
    }

    /**
     * Test that we cannot deactivate a Room Class if it is used by a Room.
     */
    public function test_cannot_deactivate_room_class_if_used_by_room()
    {
        $roomClass1 = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);
        $roomClass2 = RoomClass::create(['name' => 'Class 2', 'code' => 'C2', 'is_active' => true]); // another active one

        $roomForm = RoomForm::create(['name' => 'Form 1', 'max_adults' => 2]);

        $room = Room::create([
            'room_number' => '101',
            'room_class_id' => $roomClass1->id,
            'room_form_id' => $roomForm->id,
            'floor' => '1',
        ]);

        $response = $this->putJson("/api/room-classes/{$roomClass1->id}", [
            'name' => $roomClass1->name,
            'code' => $roomClass1->code,
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể tắt trạng thái hoạt động của loại phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng loại phòng này!'
        ]);
    }

    /**
     * Test that we cannot deactivate a Room Class if it is used by a StandardRate.
     */
    public function test_cannot_deactivate_room_class_if_used_by_standard_rate()
    {
        $roomClass1 = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);
        $roomClass2 = RoomClass::create(['name' => 'Class 2', 'code' => 'C2', 'is_active' => true]); // another active one

        $roomForm = RoomForm::create(['name' => 'Form 1', 'max_adults' => 2]);

        $rate = StandardRate::create([
            'room_class_id' => $roomClass1->id,
            'room_form_id' => $roomForm->id,
            'room_price' => 100000,
            'extra_bed_price' => 20000
        ]);

        $response = $this->putJson("/api/room-classes/{$roomClass1->id}", [
            'name' => $roomClass1->name,
            'code' => $roomClass1->code,
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể tắt trạng thái hoạt động của loại phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng loại phòng này!'
        ]);
    }

    /**
     * Test that we cannot delete the last active Room Class.
     */
    public function test_cannot_delete_last_active_room_class()
    {
        $roomClass = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);

        $response = $this->deleteJson("/api/room-classes/{$roomClass->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể xóa loại phòng này vì phải có ít nhất 1 loại phòng đang hoạt động!'
        ]);
    }

    /**
     * Test that we cannot delete a Room Class if it has Standard Rates or Rooms.
     */
    public function test_cannot_delete_room_class_with_rooms_or_rates()
    {
        $roomClass1 = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);
        $roomClass2 = RoomClass::create(['name' => 'Class 2', 'code' => 'C2', 'is_active' => true]); // another active one

        $roomForm = RoomForm::create(['name' => 'Form 1', 'max_adults' => 2]);

        $room = Room::create([
            'room_number' => '101',
            'room_class_id' => $roomClass1->id,
            'room_form_id' => $roomForm->id,
            'floor' => '1',
        ]);

        $response = $this->deleteJson("/api/room-classes/{$roomClass1->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể xóa loại phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng loại phòng này!'
        ]);
    }

    /**
     * Test that we cannot delete a Room Form if it has Standard Rates or Rooms.
     */
    public function test_cannot_delete_room_form_with_rooms_or_rates()
    {
        $roomClass = RoomClass::create(['name' => 'Class 1', 'code' => 'C1', 'is_active' => true]);
        $roomForm = RoomForm::create(['name' => 'Form 1', 'max_adults' => 2]);

        $room = Room::create([
            'room_number' => '101',
            'room_class_id' => $roomClass->id,
            'room_form_id' => $roomForm->id,
            'floor' => '1',
        ]);

        $response = $this->deleteJson("/api/room-forms/{$roomForm->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Không thể xóa dạng phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng dạng phòng này!'
        ]);
    }
}
