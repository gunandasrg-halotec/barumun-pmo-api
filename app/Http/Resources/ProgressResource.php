<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class ProgressResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'wbd_node'         => $this->whenLoaded('wbdNode', fn () => [
                'id'   => $this->wbdNode->id,
                'code' => $this->wbdNode->code,
                'name' => $this->wbdNode->name,
                'unit' => $this->wbdNode->unit,
            ]),
            'progress_date'    => $this->progress_date?->toDateString(),
            'progress_volume'  => $this->progress_volume !== null ? (float) $this->progress_volume : null,
            'note'             => $this->note,
            'status'           => $this->status,
            'is_official'      => $this->isOfficial(),
            'entered_by'       => $this->whenLoaded('enteredByUser', fn () => [
                'id'        => $this->enteredByUser->id,
                'full_name' => $this->enteredByUser->full_name,
                'role'      => $this->enteredByUser->role?->role_name,
            ]),
            'approved_by'      => $this->whenLoaded('approvedByUser', fn () => $this->approvedByUser ? [
                'id'        => $this->approvedByUser->id,
                'full_name' => $this->approvedByUser->full_name,
            ] : null),
            'approved_at'      => $this->approved_at,
            'rejected_by'      => $this->whenLoaded('rejectedByUser', fn () => $this->rejectedByUser ? [
                'id'        => $this->rejectedByUser->id,
                'full_name' => $this->rejectedByUser->full_name,
            ] : null),
            'rejected_at'      => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'actual_costs'     => $this->whenLoaded('actualCostTransactions', fn () =>
                $this->actualCostTransactions->map(fn ($c) => [
                    'id'               => $c->id,
                    'amount'           => (float) $c->amount,
                    'status'           => $c->status,
                    'transaction_date' => $c->transaction_date?->toDateString(),
                ])
            ),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
