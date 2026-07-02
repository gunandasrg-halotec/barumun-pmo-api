<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectResource extends ModelResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->load(['createdBy.role', 'activeWbdVersion']);

        return [
            'id'                   => $this->id,
            'project_code'         => $this->project_code,
            'project_name'         => $this->project_name,
            'client_name'          => $this->client_name,
            'location'             => $this->location,
            'start_date'           => $this->start_date?->toDateString(),
            'end_date'             => $this->end_date?->toDateString(),
            'status'               => $this->status?->value ?? $this->status,
            'description'          => $this->description,
            'has_active_baseline'  => $this->hasActiveBaseline(),
            'active_wbd_version'   => $this->activeWbdVersion ? [
                'id'             => $this->activeWbdVersion->id,
                'version_number' => $this->activeWbdVersion->version_number,
                'status'         => $this->activeWbdVersion->status,
            ] : null,
            'created_by'           => $this->createdBy ? [
                'id'        => $this->createdBy->id,
                'full_name' => $this->createdBy->full_name,
                'role'      => $this->createdBy->role?->role_name,
            ] : null,
            'submissions_reset_at' => $this->submissions_reset_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
