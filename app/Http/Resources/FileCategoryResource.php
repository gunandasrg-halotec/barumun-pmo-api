<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class FileCategoryResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'category_name' => $this->category_name,
            'description'   => $this->description,
            'is_active'     => (bool) $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
