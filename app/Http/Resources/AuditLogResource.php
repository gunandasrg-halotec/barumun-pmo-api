<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class AuditLogResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'entity_type'    => $this->entity_type,
            'entity_id'      => $this->entity_id,
            'action_type'    => $this->action_type,
            'action_by'      => $this->whenLoaded('actionByUser', fn () => $this->actionByUser ? [
                'id'        => $this->actionByUser->id,
                'full_name' => $this->actionByUser->full_name,
                'role'      => $this->actionByUser->role?->role_name,
            ] : null),
            'action_at'      => $this->action_at,
            'old_value'      => $this->old_value_json,
            'new_value'      => $this->new_value_json,
            'remarks'        => $this->remarks,
        ];
    }
}
