<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfoBusinessResource extends JsonResource
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
            'company_name' => $this->company_name,
            'bank_name' => $this->bank_name,
            'chairman' => $this->chairman,
            'phone' => $this->phone,
            'email' => $this->email,
            'director' => $this->director,
            'address' => $this->address,
            'system_branch_id' => $this->system_branch_id ? (int) $this->system_branch_id : null,
            'chief_accountant' => $this->chief_accountant,
            'logo_url' => $this->logo_url ? url($this->logo_url) : null,
            'logo_path' => $this->logo_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
