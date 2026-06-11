<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelSettingResource extends JsonResource
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
            'address' => $this->address,
            'tax_code' => $this->tax_code,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'facebook' => $this->facebook,
            'channel_manager' => $this->channel_manager,
            'currency' => $this->currency,
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'adult_breakfast_price' => (float) $this->adult_breakfast_price,
            'child_breakfast_price' => (float) $this->child_breakfast_price,
            'extra_bed_price' => (float) $this->extra_bed_price,
            'total_rooms' => $this->total_rooms,
            'website' => $this->website,
            'booking_prefix' => $this->booking_prefix,
            'logo_url' => $this->logo_url,
            'qr_code_url' => $this->qr_code_url,
        ];
    }
}
