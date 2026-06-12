<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelServiceResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'service_charge' => $this->service_charge,
            'tax' => $this->tax,
            'special_tax' => $this->special_tax,
            'include_service_charge' => (bool)$this->include_service_charge,
            'include_tax' => (bool)$this->include_tax,
            'include_special_tax' => (bool)$this->include_special_tax,
            'folio' => $this->folio,
            'short_name' => $this->short_name,
            'unit' => $this->unit,
            'price' => $this->price,
            'department' => $this->department,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
