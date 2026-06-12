<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'wbd_node_id',
        'progress_date',
        'progress_volume',
        'note',
        'entered_by',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'progress_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'progress_volume' => 'decimal:4',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbdNode(): BelongsTo
    {
        return $this->belongsTo(WbdNode::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function actualCostTransactions(): HasMany
    {
        return $this->hasMany(ActualCostTransaction::class);
    }

    public function isOfficial(): bool
    {
        return in_array($this->status, [
            ProgressStatus::APPROVED->value,
            ProgressStatus::AUTO_APPROVED->value,
        ]);
    }

    public function isPendingApproval(): bool
    {
        return $this->status === ProgressStatus::PENDING_PM_APPROVAL->value;
    }
}
