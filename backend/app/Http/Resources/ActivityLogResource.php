<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'employee_code' => $this->employee_code,
            'action' => $this->action,
            'module' => $this->module,
            'component' => $this->component,
            'description' => $this->description,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'target_label' => $this->target_label,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_method' => $this->request_method,
            'request_url' => $this->request_url,
            'response_status' => $this->response_status,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->diffForHumans(),
            'created_date' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'),
            'created_time' => $this->created_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i:s'),
        ];
    }
}
