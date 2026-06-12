<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_code',
        'project_name',
        'client_name',
        'location',
        'start_date',
        'end_date',
        'status',
        'description',
        'active_wbd_version_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activeWbdVersion(): BelongsTo
    {
        return $this->belongsTo(WbdVersion::class, 'active_wbd_version_id');
    }

    public function wbdVersions(): HasMany
    {
        return $this->hasMany(WbdVersion::class);
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class);
    }

    public function actualCostTransactions(): HasMany
    {
        return $this->hasMany(ActualCostTransaction::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function reportRecords(): HasMany
    {
        return $this->hasMany(ReportRecord::class);
    }

    public function hasActiveBaseline(): bool
    {
        return $this->active_wbd_version_id !== null;
    }
}
