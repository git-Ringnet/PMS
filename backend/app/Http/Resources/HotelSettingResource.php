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
            'first_name' => $this->first_name,
            'hotel_name' => $this->hotel_name,
            'hotel_name1' => $this->hotel_name1,
            'address' => $this->address,
            'address1' => $this->address1,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'website' => $this->website,
            'account' => $this->account,
            'bank_code' => $this->bank_code,
            'bank' => $this->bank,
            'tax_code' => $this->tax_code,
            'account_name' => $this->account_name,
            'serial' => $this->serial,
            'invoice_number' => $this->invoice_number,
            'invoice_number_length' => $this->invoice_number_length ? (int) $this->invoice_number_length : null,
            'form_no' => $this->form_no,
            'logo' => $this->logo,
            'invoice_address' => $this->invoice_address,
            'breakfast_adult_rate' => (float) $this->breakfast_adult_rate,
            'breakfast_child_rate' => (float) $this->breakfast_child_rate,
            'extra_bed_rate' => (float) $this->extra_bed_rate,
            'room_number' => $this->room_number,
            'division' => $this->division,
            'currency' => $this->currency,
            'prefix_booking_id' => $this->prefix_booking_id,
            'channel_manager' => $this->channel_manager,
            'facebook' => $this->facebook,
            'hotel_link' => $this->hotel_link,
            'pos_serial' => $this->pos_serial,
            'pos_invoice_number' => $this->pos_invoice_number,
            'pos_invoice_number_length' => $this->pos_invoice_number_length,
            'pos_invoice_form_no' => $this->pos_invoice_form_no,
            'pos_invoice_symbol' => $this->pos_invoice_symbol,
            'logo_url' => $this->logo_url,
            'qr_code_url' => $this->qr_code_url,
        ];
    }
}
