<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomRateCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'room_class_id' => $this->room_class_id,
            'room_class' => new RoomClassResource($this->whenLoaded('roomClass')),
            'room_form_id' => $this->room_form_id,
            'room_form' => new RoomFormResource($this->whenLoaded('roomForm')),
            'adults' => $this->adults,
            'children' => $this->children,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'price' => $this->price,
            'breakfast_price' => $this->breakfast_price,
            'extra_bed_price' => $this->extra_bed_price,
            'has_breakfast' => $this->has_breakfast,
            'is_allowed' => $this->is_allowed,
            'rate_type' => $this->rate_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
