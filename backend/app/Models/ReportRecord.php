<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'report_type',
        'period_start',
        'period_end',
        'file_path',
        'generated_by',
        'generated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
