<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'trading_name' => $this->trading_name,
            'address' => $this->address,
            'tax_code' => $this->tax_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'customer_source_id' => $this->customer_source_id,
            'customer_source' => new CustomerSourceResource($this->whenLoaded('customerSource')),
            'market_id' => $this->market_id,
            'market' => new MarketResource($this->whenLoaded('market')),
            'sync_acc' => (bool) $this->sync_acc,
            'max_debt' => (float) $this->max_debt,
            'bank_account' => $this->bank_account,
            'booker_id' => $this->booker_id,
            'booker' => new BookerResource($this->whenLoaded('booker')),
            'rate_code' => $this->rate_code,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
