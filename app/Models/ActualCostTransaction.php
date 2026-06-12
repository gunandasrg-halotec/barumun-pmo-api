<?php

namespace App\Models;

use App\Enums\CostStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActualCostTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'progress_entry_id',
        'amount',
        'transaction_date',
        'description',
        'entered_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function progressEntry(): BelongsTo
    {
        return $this->belongsTo(ProgressEntry::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isApproved(): bool
    {
        return $this->status === CostStatus::APPROVED->value;
    }

    public function isInReview(): bool
    {
        return $this->status === CostStatus::REVIEW->value;
    }
}
