<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemBranchResource extends JsonResource
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
            'tax_code' => $this->tax_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'accounting_month' => $this->accounting_month,
            'organization_type' => $this->organization_type ?? 'PMS',
            'db_connection' => $this->db_connection,
            'db_name' => \App\Services\TenantDatabaseService::getDatabaseName($this->code),
            'is_active' => (boolean) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
