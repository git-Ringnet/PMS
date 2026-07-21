<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $standardRate = $this->standardRates?->first();
        $maxAdults = $standardRate?->roomForm?->max_adults ?? 2;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'color' => $this->color,
            'is_active' => (bool) $this->is_active,
            'orders' => (int) $this->orders,
            'room_class_group_id' => $this->room_class_group_id,
            'group' => $this->roomClassGroup?->name,
            'notes' => $this->notes,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'max_adults' => $maxAdults,
            'room_form_id' => $standardRate?->room_form_id,
            'room_form_name' => $standardRate?->roomForm?->name,
            'room_price' => $standardRate?->room_price ? (float) $standardRate->room_price : 0,
            'extra_bed_price' => $standardRate?->extra_bed_price ? (float) $standardRate->extra_bed_price : 0,
        ];
    }
}
