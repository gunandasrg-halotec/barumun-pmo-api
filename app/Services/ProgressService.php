<?php

namespace App\Services;

use App\Enums\ProgressStatus;
use App\Enums\RoleName;
use App\Models\ActualCostTransaction;
use App\Models\Notification;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\User;
use App\Models\WbdNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        // Guard: node sudah ditandai selesai (remaining_volume = 0)
        $isCompleted = ProgressEntry::where('wbd_node_id', $node->id)
            ->whereIn('status', [
                ProgressStatus::APPROVED->value,
                ProgressStatus::AUTO_APPROVED->value,
                ProgressStatus::PENDING_PM_APPROVAL->value,
            ])
            ->whereNotNull('remaining_volume')
            ->where('remaining_volume', 0)
            ->exists();

        if ($isCompleted) {
            throw new \RuntimeException(
                'Pekerjaan ini sudah ditandai selesai. Tidak dapat menambahkan progress baru.'
            );
        }

        // Guard: total volume (existing + new) must not exceed planned volume
        if ($node->volume !== null && $node->volume > 0) {
            $existingVolume = ProgressEntry::where('wbd_node_id', $node->id)
                ->whereIn('status', [
                    ProgressStatus::APPROVED->value,
                    ProgressStatus::AUTO_APPROVED->value,
                    ProgressStatus::PENDING_PM_APPROVAL->value,
                ])
                ->sum('progress_volume');

            $remaining = $node->volume - $existingVolume;

            if ($data['progress_volume'] > $remaining) {
                throw new \RuntimeException(
                    "Volume realisasi melebihi volume rencana. " .
                    "Sisa volume yang dapat diinput: {$remaining} {$node->unit}."
                );
            }
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

            // Create cost transaction if actual_cost is provided
            $actualCost = $data['actual_cost'] ?? null;
            if ($actualCost !== null && $actualCost > 0) {
                ActualCostTransaction::create([
                    'project_id'        => $project->id,
                    'progress_entry_id' => $progress->id,
                    'amount'            => $actualCost,
                    'transaction_date'  => $data['progress_date'],
                    'description'       => $data['note'] ?? null,
                    'entered_by'        => $enteredBy->id,
                    'status'            => 'APPROVED',
                ]);
            }

            // Store attachment if provided
            if (!empty($data['attachment'])) {
                $file = $data['attachment'];
                $path = $file->store('progress-attachments/' . $project->id, 'local');
                $progress->update(['attachment_path' => $path]);
            }

            // Handle remaining_volume: pakai nilai dari user, atau hitung otomatis
            $remainingVolume = isset($data['remaining_volume']) && $data['remaining_volume'] !== ''
                ? (float) $data['remaining_volume']
                : ($node->volume !== null
                    ? max(0, (float) $node->volume - (float) $data['progress_volume'])
                    : null);

            $progress->update(['remaining_volume' => $remainingVolume]);

            // Kirim notifikasi ke Direktur jika realisasi + sisa > rencana
            if (
                $remainingVolume !== null &&
                $node->volume !== null &&
                ((float) $data['progress_volume'] + $remainingVolume) > (float) $node->volume
            ) {
                $directors = User::whereHas('role', fn ($q) =>
                    $q->where('role_name', RoleName::DIREKSI->value)
                )->get();

                foreach ($directors as $director) {
                    Notification::create([
                        'user_id'      => $director->id,
                        'triggered_by' => $enteredBy->id,
                        'type'         => 'OVER_BUDGET_RISK',
                        'title'        => 'Potensi Over Budget: ' . $node->name,
                        'message'      => sprintf(
                            '%s mencatat realisasi %s %s dengan estimasi sisa %s %s, ' .
                            'melebihi rencana %s %s untuk item "%s" pada proyek "%s".',
                            $enteredBy->full_name,
                            number_format((float) $data['progress_volume'], 2, '.', ','),
                            $node->unit,
                            number_format($remainingVolume, 2, '.', ','),
                            $node->unit,
                            number_format((float) $node->volume, 2, '.', ','),
                            $node->unit,
                            $node->name,
                            $project->name
                        ),
                        'data' => [
                            'project_id'        => $project->id,
                            'project_name'      => $project->name,
                            'wbd_node_id'       => $node->id,
                            'wbd_node_name'     => $node->name,
                            'progress_entry_id' => $progress->id,
                            'volume_plan'       => (float) $node->volume,
                            'volume_actual'     => (float) $data['progress_volume'],
                            'volume_remaining'  => $remainingVolume,
                        ],
                    ]);
                }
            }

            $this->auditLog->logCreate('progress_entry', $progress->id, [
                'status' => $status,
                'entered_by_role' => $enteredBy->role->role_name,
            ]);

            return $progress->fresh([
                'project', 'wbdNode', 'enteredByUser.role', 'approvedByUser', 'actualCostTransactions',
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
