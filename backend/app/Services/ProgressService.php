<?php

namespace App\Services;

use App\Enums\ProgressStatus;
use App\Enums\RoleName;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\User;
use App\Models\WbdNode;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(private AuditLogService $auditLog) {}

    /**
     * Create a new progress entry.
     * Business rules:
     * - project must have active baseline
     * - node must be ITEM type (operasional)
     * - Admin Proyek → PENDING_PM_APPROVAL
     * - Project Manager → AUTO_APPROVED
     */
    public function createProgress(
        Project $project,
        WbdNode $node,
        array $data,
        User $enteredBy
    ): ProgressEntry {
        // Guard: project must have active baseline
        if (!$project->hasActiveBaseline()) {
            throw new \RuntimeException('Project does not have an active approved baseline. Cannot create progress.');
        }

        // Guard: node must be ITEM type
        if (!$node->isItem()) {
            throw new \RuntimeException('Progress can only be created for ITEM-type WBD nodes.');
        }

        // Guard: volume must be > 0
        if (($data['progress_volume'] ?? 0) <= 0) {
            throw new \RuntimeException('Progress volume must be greater than 0.');
        }

        return DB::transaction(function () use ($project, $node, $data, $enteredBy) {
            $status = $enteredBy->isProjectManager()
                ? ProgressStatus::AUTO_APPROVED->value
                : ProgressStatus::PENDING_PM_APPROVAL->value;

            $progress = ProgressEntry::create([
                'project_id' => $project->id,
                'wbd_node_id' => $node->id,
                'progress_date' => $data['progress_date'],
                'progress_volume' => $data['progress_volume'],
                'note' => $data['note'] ?? null,
                'entered_by' => $enteredBy->id,
                'status' => $status,
            ]);

            if ($enteredBy->isProjectManager()) {
                $progress->update([
                    'approved_by' => $enteredBy->id,
                    'approved_at' => now(),
                ]);
            }

            $this->auditLog->logCreate('progress_entry', $progress->id, [
                'status' => $status,
                'entered_by_role' => $enteredBy->role->role_name,
            ]);

            return $progress->fresh([
                'project', 'wbdNode', 'enteredByUser.role', 'approvedByUser',
            ]);
        });
    }

    /**
     * Approve a pending progress entry (Project Manager only).
     */
    public function approveProgress(ProgressEntry $progress, User $approvedBy): ProgressEntry
    {
        if (!$approvedBy->canApproveProgress()) {
            throw new \RuntimeException('Only Project Manager can approve progress.');
        }

        if (!$progress->isPendingApproval()) {
            throw new \RuntimeException('Progress must be in PENDING_PM_APPROVAL status to be approved.');
        }

        return DB::transaction(function () use ($progress, $approvedBy) {
            $progress->update([
                'status' => ProgressStatus::APPROVED->value,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
            ]);

            $this->auditLog->logApprove('progress_entry', $progress->id);

            return $progress->fresh();
        });
    }

    /**
     * Reject a pending progress entry (Project Manager only).
     */
    public function rejectProgress(ProgressEntry $progress, User $rejectedBy, string $reason): ProgressEntry
    {
        if (!$rejectedBy->canApproveProgress()) {
            throw new \RuntimeException('Only Project Manager can reject progress.');
        }

        if (!$progress->isPendingApproval()) {
            throw new \RuntimeException('Progress must be in PENDING_PM_APPROVAL status to be rejected.');
        }

        return DB::transaction(function () use ($progress, $rejectedBy, $reason) {
            $progress->update([
                'status' => ProgressStatus::REJECTED->value,
                'rejected_by' => $rejectedBy->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditLog->logReject('progress_entry', $progress->id, $reason);

            return $progress->fresh();
        });
    }
}
