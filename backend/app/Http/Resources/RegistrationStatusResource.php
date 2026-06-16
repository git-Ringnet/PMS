<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'confirmation_days' => $this->confirmation_days,
            'description' => $this->description,
            'status_value' => $this->status_value,
            'is_hidden' => $this->is_hidden,
            'is_availability' => $this->is_availability,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
