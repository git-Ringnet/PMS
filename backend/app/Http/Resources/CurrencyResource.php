<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'country' => $this->country,
            'short_name' => $this->short_name,
            'decimals_to_round' => $this->decimals_to_round,
            'is_main' => $this->is_main,
            'is_active' => $this->is_active,
            'exchange_rate' => $this->exchange_rate,
            'image_path' => $this->image_path ? asset($this->image_path) : null,
            'image_raw' => $this->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
