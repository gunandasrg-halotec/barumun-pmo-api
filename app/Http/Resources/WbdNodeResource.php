<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class WbdNodeResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'wbd_version_id'    => $this->wbd_version_id,
            'parent_node_id'    => $this->parent_node_id,
            'node_type'         => $this->node_type,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'unit'              => $this->unit,
            'volume'            => $this->volume !== null ? (float) $this->volume : null,
            'rate'              => $this->rate !== null ? (float) $this->rate : null,
            'planned_cost'      => $this->planned_cost !== null ? (float) $this->planned_cost : null,
            'component_percent' => $this->component_percent !== null ? (float) $this->component_percent : null,
            'total_percent'     => $this->total_percent !== null ? (float) $this->total_percent : null,
            'start_date'        => $this->start_date?->toDateString(),
            'duration_days'     => $this->duration_days,
            'end_date'          => $this->end_date?->toDateString(),
            'status'            => $this->status,
            'sort_order'        => $this->sort_order,
            'latest_remaining_volume' => $this->progressEntries()
                ->whereNotNull('remaining_volume')
                ->orderByDesc('progress_date')
                ->orderByDesc('created_at')
                ->value('remaining_volume'),
            'latest_remaining_cost' => $this->progressEntries()
                ->whereNotNull('remaining_cost')
                ->orderByDesc('progress_date')
                ->orderByDesc('created_at')
                ->value('remaining_cost'),
            'children'          => $this->when(
                $this->relationLoaded('children'),
                fn () => WbdNodeResource::collection($this->children)
            ),
        ];
    }
}
