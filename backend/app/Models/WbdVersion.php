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
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_active' => 'boolean',
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
}
