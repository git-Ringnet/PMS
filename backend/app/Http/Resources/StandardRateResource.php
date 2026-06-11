<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StandardRateResource extends JsonResource
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
            'room_class_id' => $this->room_class_id,
            'room_form_id' => $this->room_form_id,
            'room_class' => new RoomClassResource($this->whenLoaded('roomClass')),
            'room_form' => new RoomFormResource($this->whenLoaded('roomForm')),
            'room_price' => (float) $this->room_price,
            'extra_bed_price' => (float) $this->extra_bed_price,
        ];
    }
}
