<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'account' => $this->account,
            'account_name' => $this->account_name,
            'bank_name' => $this->bank_name,
            'service_charge' => $this->service_charge,
            'department' => $this->department,
            'payment_group' => $this->payment_group,
            'is_free' => $this->is_free,
            'is_inactive' => $this->is_inactive,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
