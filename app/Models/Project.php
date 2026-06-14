<?php

namespace App\Models;

use App\Enums\ProjectStatus;
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
            'end_date'   => 'date',
            'status'     => ProjectStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function hasActiveBaseline(): bool
    {
        return $this->active_wbd_version_id !== null;
    }

    // ─── Query Scope ─────────────────────────────────────────────────────────

    /**
     * Reusable scope for list / search / filter / sort.
     * Usage: Project::projects($queryParams)->paginate()
     */
    public function scopeProjects($query, array $queryParams): void
    {
        $query
            ->with(['createdBy.role', 'activeWbdVersion'])
            ->when($queryParams['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('project_name', 'like', "%{$search}%")
                          ->orWhere('project_code', 'like', "%{$search}%")
                          ->orWhere('client_name',  'like', "%{$search}%");
                });
            })
            ->when($queryParams['filter'] ?? null, function ($q, $filter) {
                if (array_key_exists('status', $filter)) {
                    $q->where('status', $filter['status']);
                }
                if (array_key_exists('client_name', $filter)) {
                    $q->where('client_name', 'like', '%' . $filter['client_name'] . '%');
                }
            })
            ->when($queryParams['sort-by'] ?? null, function ($q, $sortBy) use ($queryParams) {
                $sortDir = $queryParams['sort-dir'] ?? 'asc';
                $q->orderBy($sortBy, $sortDir);
            }, function ($q) {
                $q->orderBy('project_name', 'asc');
            });
    }
}
