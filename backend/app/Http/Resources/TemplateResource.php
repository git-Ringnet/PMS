<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
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
            'group' => $this->group,
            'name' => $this->name,
            'report' => $this->report,
            'report_data_source_id' => $this->report_data_source_id,
            'parameter_defaults' => $this->parameter_defaults,
            'report_data_source' => $this->whenLoaded('reportDataSource'),
            'page_size' => $this->page_size,
            'page_orientation' => $this->page_orientation,
            'margin_top' => $this->margin_top,
            'margin_bottom' => $this->margin_bottom,
            'margin_left' => $this->margin_left,
            'margin_right' => $this->margin_right,
            'content_json' => $this->content_json,
            'content_html' => $this->content_html,
            'css' => $this->css,
            'is_default' => $this->is_default,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
