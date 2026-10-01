<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectingRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_model_persists_and_returns_connecting_room()
    {
        $roomForm = RoomForm::firstOrCreate(
            ['code' => 'TEST_FORM'],
            ['name' => 'Test Form']
        );
        $roomClass = RoomClass::firstOrCreate(
            ['code' => 'TEST_CLASS'],
            ['name' => 'Test Class', 'color' => '#ffffff']
        );

        $room1 = Room::updateOrCreate(
            ['room_number' => 'TEST102'],
            [
                'room_form_id' => $roomForm->id,
                'room_class_id' => $roomClass->id,
                'floor' => '1',
                'max_guests' => 2,
                'connecting_room' => 'TEST103',
            ]
        );

        $this->assertEquals('TEST103', $room1->connecting_room);

        $room2 = Room::updateOrCreate(
            ['room_number' => 'TEST103'],
            [
                'room_form_id' => $roomForm->id,
                'room_class_id' => $roomClass->id,
                'floor' => '1',
                'max_guests' => 2,
                'connecting_room' => null,
            ]
        );

        $this->assertNull($room2->connecting_room);

        // Clean up
        $room1->delete();
        $room2->delete();
    }

    public function test_room_resource_includes_connecting_room()
    {
        $room = new Room([
            'room_number' => 'TEST104',
            'connecting_room' => 'TEST105',
            'max_guests' => 2,
            'floor' => 1,
        ]);

        $resource = (new \App\Http\Resources\RoomResource($room))->toArray(request());

        $this->assertArrayHasKey('connecting_room', $resource);
        $this->assertEquals('TEST105', $resource['connecting_room']);
    }

    public function test_cannot_connect_room_to_itself()
    {
        $user = User::factory()->create();
        $roomForm = RoomForm::firstOrCreate(
            ['code' => 'TEST_FORM2'],
            ['name' => 'Test Form 2']
        );
        $roomClass = RoomClass::firstOrCreate(
            ['code' => 'TEST_CLASS2'],
            ['name' => 'Test Class 2', 'color' => '#ffffff']
        );

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/rooms', [
            'room_number' => 'TEST999',
            'connecting_room' => 'TEST999', // same as room_number
            'room_form_id' => $roomForm->id,
            'room_class_id' => $roomClass->id,
            'floor' => '1',
            'max_guests' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('connecting_room');
    }
}
