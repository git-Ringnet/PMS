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
            'room_class_group_id' => $this->room_class_group_id,
            'group' => $this->roomClassGroup?->name,
            'notes' => $this->notes,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'max_adults' => $maxAdults,
        ];
    }
}
