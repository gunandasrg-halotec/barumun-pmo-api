<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'related_entity_type',
        'related_entity_id',
        'file_category_id',
        'file_type',
        'original_file_name',
        'storage_path',
        'mime_type',
        'caption',
        'photo_date',
        'note',
        'uploaded_by',
        'uploaded_at',
        'file_status',
    ];

    protected function casts(): array
    {
        return [
            'photo_date' => 'date',
            'uploaded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fileCategory(): BelongsTo
    {
        return $this->belongsTo(FileCategory::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->file_type === 'IMAGE';
    }

    public function isDocument(): bool
    {
        return $this->file_type === 'DOCUMENT';
    }
}
