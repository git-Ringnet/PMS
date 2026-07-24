<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cutoff = (int)($this->cut_off_day ?? 0);
        return [
            'id' => $this->id,
            'booking_status_id' => $this->booking_status_id ?? $this->id,
            'BookingStatusId' => $this->booking_status_id ?? $this->id,
            'name' => $this->name,
            'booking_status_name' => $this->name,
            'BookingStatusName' => $this->name,
            'color' => $this->color,
            'booking_status_color' => $this->color,
            'BookingStatusColor' => $this->color,
            'cut_off_day' => $cutoff,
            'cutoff_day' => $cutoff,
            'CutoffDay' => $cutoff,
            'confirmation_days' => $cutoff,
            'description' => $this->description,
            'status_value' => $this->status_value,
            'is_hidden' => (bool)$this->is_hidden,
            'is_availability' => (bool)$this->is_availability,
            'bk_definite' => $this->bk_definite,
            'vietnamese' => $this->vietnamese,
            'english' => $this->english,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
