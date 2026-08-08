<?php

namespace App\Models;

use App\Enums\WbdVersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbdVersion extends Model
{
    use HasUuids;

    protected $table = 'wbd_versions';

    protected $fillable = [
        'project_id',
        'version_number',
        'status',
        'based_on_version_id',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'is_active',
        'is_baseline_revision',
        'revision_unlocked_by',
        'revision_unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_active' => 'boolean',
            'is_baseline_revision' => 'boolean',
            'revision_unlocked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function basedOnVersion(): BelongsTo
    {
        return $this->belongsTo(WbdVersion::class, 'based_on_version_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WbdNode::class)->orderBy('sort_order');
    }

    public function rootNodes(): HasMany
    {
        return $this->hasMany(WbdNode::class)->whereNull('parent_node_id')->orderBy('sort_order');
    }

    public function isFinalApproved(): bool
    {
        return $this->status === WbdVersionStatus::FINAL_APPROVED->value;
    }

    public function isDraft(): bool
    {
        return $this->status === WbdVersionStatus::DRAFT->value;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value;
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function isBaselineRevision(): bool
    {
        return (bool) $this->is_baseline_revision;
    }

    public function isRevisionUnlocked(): bool
    {
        return $this->revision_unlocked_by !== null;
    }

    public function revisionUnlockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_unlocked_by');
    }

    /** Revisi baseline lain (dari baseline yang sama) yang belum diputuskan Direksi. */
    public function revisions(): HasMany
    {
        return $this->hasMany(WbdVersion::class, 'based_on_version_id')
            ->where('is_baseline_revision', true);
    }
}
