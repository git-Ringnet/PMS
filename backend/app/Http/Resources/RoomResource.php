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
            'orders' => (int) $this->orders,
            'room_form_id' => $this->room_form_id,
            'room_class_id' => $this->room_class_id,
            'room_form' => new RoomFormResource($this->whenLoaded('roomForm')),
            'room_class' => new RoomClassResource($this->whenLoaded('roomClass')),
            'room_type' => $this->roomClass?->code,
            'room_type_name' => $this->roomClass?->name,
            'is_clean' => !in_array($this->room_status_code, ['vacant_dirty', 'occupied_dirty']),
            'has_issue' => in_array($this->room_status_code, ['ooo', 'oos', 'occupied_ooo', 'housekeeping']),
            'room_status_code' => $this->room_status_code ?? 'vacant_ready',
            'room_status_icon' => \App\Models\RoomStatus::getIcon($this->room_status_code ?? 'vacant_ready'),
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
            'booking_status' => $this->booking_status ?? null,
            'guest_name' => $this->guest_name ?? '',
            'booking_code' => $this->booking_code ?? '',
            'booking_name' => $this->booking_name ?? '',
            'company_name' => $this->company_name ?? '',
            'notes' => $this->notes,
            'lock_id' => $this->status === 'maintenance' ? $this->activeLock?->id : null,
            'lock_type' => $this->status === 'maintenance' ? ($this->lock_type ?? $this->activeLock?->lock_type) : null,
            'lock_start_date' => $this->status === 'maintenance' ? ($this->activeLock?->start_date instanceof \DateTimeInterface ? $this->activeLock?->start_date?->format('Y-m-d H:i:s') : $this->activeLock?->start_date) : null,
            'lock_end_date' => $this->status === 'maintenance' ? ($this->activeLock?->end_date instanceof \DateTimeInterface ? $this->activeLock?->end_date?->format('Y-m-d H:i:s') : $this->activeLock?->end_date) : null,
            'lock_reason' => $this->status === 'maintenance' ? $this->activeLock?->reason : null,
            'lock_maintenance_percent' => $this->status === 'maintenance' ? ($this->activeLock?->maintenance_percent ?? 0) : 0,
            'lock_status' => $this->status === 'maintenance' ? ($this->activeLock?->status ?? '') : '',
            'lock_username' => $this->status === 'maintenance' ? ($this->activeLock?->username ?? '') : '',
            'active_locks' => $this->relationLoaded('allActiveLocks') ? $this->allActiveLocks->map(fn($lock) => [
                'id' => $lock->id,
                'lock_id' => $lock->id,
                'lock_type' => $lock->lock_type,
                'lock_start_date' => $lock->start_date instanceof \DateTimeInterface ? $lock->start_date?->format('Y-m-d H:i:s') : $lock->start_date,
                'lock_end_date' => $lock->end_date instanceof \DateTimeInterface ? $lock->end_date?->format('Y-m-d H:i:s') : $lock->end_date,
                'lock_reason' => $lock->reason,
                'lock_maintenance_percent' => $lock->maintenance_percent ?? 0,
                'lock_status' => $lock->status ?? '',
                'lock_username' => $lock->username ?? '',
                'unlock_username' => $lock->unlock_username ?? '',
                'unlocked_at' => $lock->unlocked_at instanceof \DateTimeInterface ? $lock->unlocked_at?->format('Y-m-d H:i:s') : $lock->unlocked_at,
                'is_future' => \Carbon\Carbon::parse($lock->start_date)->gt(now()),
            ]) : [],
            'booking_color' => $this->booking_color,
            'arrival_date' => $this->arrival_date,
            'departure_date' => $this->departure_date,
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'babies' => $this->babies,
            'arrival_time' => $this->arrival_time,
            'rate' => $this->rate,
            'booking_note' => $this->booking_note,
            'special_requests' => $this->special_requests,
            'guest_details' => $this->guest_details,
            'booking_status' => $this->booking_status,
            'external_booking_code' => $this->external_booking_code,
            'registration_status' => $this->registration_status,
            'confirm_date' => $this->confirm_date,
            'sales_person' => $this->sales_person,
            'is_git' => $this->is_git,
            'has_vat' => $this->has_vat,
            'payment_method' => $this->payment_method,
            'payment_value' => $this->payment_value,
            'is_do_not_move' => (int)($this->is_do_not_move ?? 0),
            'booking_room_id' => $this->booking_room_id ?? null,
            'booking_id' => $this->booking_id ?? null,
        ];
    }
}
