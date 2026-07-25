<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class HeavyEquipmentResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'type'       => $this->type,
            'brand'      => $this->brand,
            'is_active'  => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
