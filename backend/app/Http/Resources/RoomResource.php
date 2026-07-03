<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'room_form_id' => $this->room_form_id,
            'room_class_id' => $this->room_class_id,
            'room_form' => new RoomFormResource($this->whenLoaded('roomForm')),
            'room_class' => new RoomClassResource($this->whenLoaded('roomClass')),
            'room_type' => $this->roomClass?->code,
            'room_type_name' => $this->roomClass?->name,
            'is_clean' => $this->status !== 'dirty',
            'has_issue' => $this->status === 'maintenance',
            'max_guests' => $this->max_guests,
            'floor' => (int) $this->floor,
            'area' => $this->area,
            'extra_beds_limit' => $this->extra_beds_limit,
            'grid_row' => $this->grid_row,
            'grid_column' => $this->grid_column,
            'owner_room' => $this->owner_room,
            'linked_room' => $this->linked_room,
            'is_internal' => (bool) $this->is_internal,
            'status' => $this->status,
            'notes' => $this->notes,
            'lock_id' => $this->activeLock?->id,
            'lock_type' => $this->activeLock?->lock_type,
            'lock_start_date' => $this->activeLock?->start_date instanceof \DateTimeInterface ? $this->activeLock?->start_date?->format('Y-m-d H:i:s') : $this->activeLock?->start_date,
            'lock_end_date' => $this->activeLock?->end_date instanceof \DateTimeInterface ? $this->activeLock?->end_date?->format('Y-m-d H:i:s') : $this->activeLock?->end_date,
            'lock_reason' => $this->activeLock?->reason,
            'lock_maintenance_percent' => $this->activeLock?->maintenance_percent ?? 0,
            'lock_status' => $this->activeLock?->status ?? '',
            'lock_username' => $this->activeLock?->username ?? '',
        ];
    }
}
