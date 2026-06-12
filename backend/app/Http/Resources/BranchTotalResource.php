<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchTotalResource extends JsonResource
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
            'api_url' => $this->api_url,
            'api_report_url' => $this->api_report_url,
            'is_master' => (bool)$this->is_master,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
