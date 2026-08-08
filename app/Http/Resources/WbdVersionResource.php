<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class WbdVersionResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        $this->load(['project', 'submittedByUser', 'approvedByUser', 'rejectedByUser']);

        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'project'              => $this->whenLoaded('project', fn () => [
                'id'           => $this->project->id,
                'project_code' => $this->project->project_code,
                'project_name' => $this->project->project_name,
            ]),
            'version_number'       => $this->version_number,
            'status'               => $this->status,
            'is_active'            => (bool) $this->is_active,
            'based_on_version_id'  => $this->based_on_version_id,
            'is_baseline_revision' => (bool) $this->is_baseline_revision,
            'revision_unlocked_by' => $this->revision_unlocked_by,
            'revision_unlocked_at' => $this->revision_unlocked_at,
            'submitted_by'         => $this->submittedByUser ? [
                'id'        => $this->submittedByUser->id,
                'full_name' => $this->submittedByUser->full_name,
            ] : null,
            'submitted_at'         => $this->submitted_at,
            'approved_by'          => $this->approvedByUser ? [
                'id'        => $this->approvedByUser->id,
                'full_name' => $this->approvedByUser->full_name,
            ] : null,
            'approved_at'          => $this->approved_at,
            'rejected_by'          => $this->rejectedByUser ? [
                'id'        => $this->rejectedByUser->id,
                'full_name' => $this->rejectedByUser->full_name,
            ] : null,
            'rejected_at'          => $this->rejected_at,
            'rejection_reason'     => $this->rejection_reason,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
