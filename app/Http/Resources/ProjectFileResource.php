<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class ProjectFileResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'project_id'          => $this->project_id,
            'file_type'           => $this->file_type,
            'original_file_name'  => $this->original_file_name,
            'mime_type'           => $this->mime_type,
            'caption'             => $this->caption,
            'photo_date'          => $this->photo_date?->toDateString(),
            'note'                => $this->note,
            'related_entity_type' => $this->related_entity_type,
            'related_entity_id'   => $this->related_entity_id,
            'file_status'         => $this->file_status,
            'file_category'       => $this->whenLoaded('fileCategory', fn () => $this->fileCategory ? [
                'id'            => $this->fileCategory->id,
                'category_name' => $this->fileCategory->category_name,
            ] : null),
            'uploaded_by'         => $this->whenLoaded('uploadedByUser', fn () => $this->uploadedByUser ? [
                'id'        => $this->uploadedByUser->id,
                'full_name' => $this->uploadedByUser->full_name,
            ] : null),
            'uploaded_at'         => $this->uploaded_at,
        ];
    }
}
