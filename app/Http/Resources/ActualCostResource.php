<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class ActualCostResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'progress_entry'   => $this->whenLoaded('progressEntry', fn () => $this->progressEntry ? [
                'id'            => $this->progressEntry->id,
                'progress_date' => $this->progressEntry->progress_date?->toDateString(),
                'status'        => $this->progressEntry->status,
                'wbd_node'      => $this->progressEntry->relationLoaded('wbdNode') && $this->progressEntry->wbdNode ? [
                    'id'   => $this->progressEntry->wbdNode->id,
                    'code' => $this->progressEntry->wbdNode->code,
                    'name' => $this->progressEntry->wbdNode->name,
                ] : null,
            ] : null),
            'amount'           => $this->amount !== null ? (float) $this->amount : null,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'description'      => $this->description,
            'status'           => $this->status,
            'entered_by'       => $this->whenLoaded('enteredByUser', fn () => $this->enteredByUser ? [
                'id'        => $this->enteredByUser->id,
                'full_name' => $this->enteredByUser->full_name,
                'role'      => $this->enteredByUser->role?->role_name,
            ] : null),
            'reviewed_by'      => $this->whenLoaded('reviewedByUser', fn () => $this->reviewedByUser ? [
                'id'        => $this->reviewedByUser->id,
                'full_name' => $this->reviewedByUser->full_name,
            ] : null),
            'reviewed_at'      => $this->reviewed_at,
            'rejected_by'      => $this->whenLoaded('rejectedByUser', fn () => $this->rejectedByUser ? [
                'id'        => $this->rejectedByUser->id,
                'full_name' => $this->rejectedByUser->full_name,
            ] : null),
            'rejected_at'      => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
