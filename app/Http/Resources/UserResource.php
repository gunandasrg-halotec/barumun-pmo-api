<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Core\ModelResource;
use OpenApi\Attributes as OA;



class UserResource extends ModelResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->load("role");
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->role_name,
            ],
            'created_at' => $this->created_at
        ];
    }
}
