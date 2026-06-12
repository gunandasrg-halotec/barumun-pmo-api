<?php

namespace App\Services;

use App\Enums\CostStatus;
use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CostService
{
    public function __construct(private AuditLogService $auditLog) {}

    /**
     * Create an actual cost transaction.
     * Business rules:
     * - progress_entry_id is mandatory and must be valid
     * - Admin Proyek input → REVIEW status
     * - Finance input → REVIEW status (Finance also needs to approve separately)
     * - PM cannot input cost
     */
    public function createCostTransaction(
        Project $project,
        ProgressEntry $progressEntry,
        array $data,
        User $enteredBy
    ): ActualCostTransaction {
        if (!$enteredBy->canInputCost()) {
            throw new \RuntimeException('You do not have permission to input cost transactions.');
        }

        if (($data['amount'] ?? 0) <= 0) {
            throw new \RuntimeException('Cost amount must be greater than 0.');
        }

        if ($progressEntry->project_id !== $project->id) {
            throw new \RuntimeException('Progress entry does not belong to the specified project.');
        }

        return DB::transaction(function () use ($project, $progressEntry, $data, $enteredBy) {
            $cost = ActualCostTransaction::create([
                'project_id' => $project->id,
                'progress_entry_id' => $progressEntry->id,
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? null,
                'entered_by' => $enteredBy->id,
                'status' => CostStatus::REVIEW->value,
            ]);

            $this->auditLog->logCreate('actual_cost_transaction', $cost->id, [
                'status' => CostStatus::REVIEW->value,
                'entered_by_role' => $enteredBy->role->role_name,
            ]);

            return $cost->fresh([
                'project', 'progressEntry.wbdNode', 'enteredByUser.role',
            ]);
        });
    }

    /**
     * Approve a cost transaction (Finance only).
     */
    public function approveCost(ActualCostTransaction $cost, User $reviewedBy): ActualCostTransaction
    {
        if (!$reviewedBy->canApproveCost()) {
            throw new \RuntimeException('Only Finance can approve cost transactions.');
        }

        if (!$cost->isInReview()) {
            throw new \RuntimeException('Cost must be in REVIEW status to be approved.');
        }

        return DB::transaction(function () use ($cost, $reviewedBy) {
            $cost->update([
                'status' => CostStatus::APPROVED->value,
                'reviewed_by' => $reviewedBy->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLog->logApprove('actual_cost_transaction', $cost->id);

            return $cost->fresh();
        });
    }

    /**
     * Reject a cost transaction (Finance only).
     */
    public function rejectCost(ActualCostTransaction $cost, User $rejectedBy, string $reason): ActualCostTransaction
    {
        if (!$rejectedBy->canApproveCost()) {
            throw new \RuntimeException('Only Finance can reject cost transactions.');
        }

        if (!$cost->isInReview()) {
            throw new \RuntimeException('Cost must be in REVIEW status to be rejected.');
        }

        return DB::transaction(function () use ($cost, $rejectedBy, $reason) {
            $cost->update([
                'status' => CostStatus::REJECTED->value,
                'rejected_by' => $rejectedBy->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditLog->logReject('actual_cost_transaction', $cost->id, $reason);

            return $cost->fresh();
        });
    }
}
