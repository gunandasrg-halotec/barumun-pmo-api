<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class ReportResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'project_id'   => $this->project_id,
            'project'      => $this->whenLoaded('project', fn () => $this->project ? [
                'id'           => $this->project->id,
                'project_code' => $this->project->project_code,
                'project_name' => $this->project->project_name,
            ] : null),
            'report_type'  => $this->report_type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end'   => $this->period_end?->toDateString(),
            'file_path'    => $this->file_path,
            'status'       => $this->status,
            'generated_by' => $this->whenLoaded('generatedByUser', fn () => $this->generatedByUser ? [
                'id'        => $this->generatedByUser->id,
                'full_name' => $this->generatedByUser->full_name,
            ] : null),
            'generated_at' => $this->generated_at,
            'created_at'   => $this->created_at,
        ];
    }
}
