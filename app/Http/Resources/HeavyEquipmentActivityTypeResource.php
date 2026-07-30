<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class HeavyEquipmentActivityTypeResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'name'             => $this->name,
            'unit'             => $this->unit,
            'allow_date_range' => (bool) $this->allow_date_range,
            'has_description'  => (bool) $this->has_description,
            'has_repair_cost'  => (bool) $this->has_repair_cost,
            'sort_order'       => (int) $this->sort_order,
            'is_active'        => (bool) $this->is_active,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
