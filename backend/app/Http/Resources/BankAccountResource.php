<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'bank_account_number' => $this->bank_account_number,
            'accounting_account' => $this->accounting_account,
            'currency_code' => $this->currency_code,
            'bank_name' => $this->bank_name,
            'opened_on' => $this->opened_on?->toDateString(),
            'closed_on' => $this->closed_on?->toDateString(),
            'description' => $this->description,
            'is_intermediary' => (bool) $this->is_intermediary,
            'is_active' => (bool) $this->is_active,
            'deleted_at' => $this->deleted_at,
            'payment_count' => $this->when(isset($this->payment_count), (int) $this->payment_count),
        ];
    }
}
