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
    public function __construct(
        private AuditLogService $auditLog,
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * Create a new progress entry.
     * Business rules:
     * - project must have active baseline
     * - node must be ITEM type (operasional)
     * - Admin Proyek → PENDING_PM_APPROVAL
     * - Manajer Kebun → AUTO_APPROVED
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

        // Guard: progress_date tidak boleh lebih awal dari entry terbaru (berdasarkan progress_date)
        $latestEntry = ProgressEntry::where('wbd_node_id', $node->id)
            ->whereIn('status', [
                ProgressStatus::APPROVED->value,
                ProgressStatus::AUTO_APPROVED->value,
                ProgressStatus::PENDING_PM_APPROVAL->value,
            ])
            ->orderByDesc('progress_date')
            ->first();

        if ($latestEntry && isset($data['progress_date'])) {
            $newDate = \Carbon\Carbon::parse($data['progress_date']);
            $latestDate = \Carbon\Carbon::parse($latestEntry->progress_date);
            if ($newDate->lt($latestDate)) {
                throw new \RuntimeException(
                    'Tanggal progress tidak boleh lebih awal dari entry terakhir (' .
                    $latestDate->format('d M Y') . ').'
                );
            }
        }

        // Cek apakah volume melebihi rencana
        $isOverVolume = false;
        $existingVolume = 0.0;
        if ($node->volume !== null && $node->volume > 0) {
            $existingVolume = (float) ProgressEntry::where('wbd_node_id', $node->id)
                ->whereIn('status', [
                    ProgressStatus::APPROVED->value,
                    ProgressStatus::AUTO_APPROVED->value,
                    ProgressStatus::PENDING_PM_APPROVAL->value,
                    ProgressStatus::PENDING_DIRECTOR_APPROVAL->value,
                ])
                ->sum('progress_volume');

            $remaining = (float) $node->volume - $existingVolume;

            if ((float) $data['progress_volume'] > $remaining) {
                $isOverVolume = true;
            }
        }

        // Cek apakah biaya realisasi melebihi rencana — pola identik dengan volume di atas.
        $isOverCost = false;
        $existingCost = 0.0;
        $actualCostInput = (float) ($data['actual_cost'] ?? 0);
        if ($node->planned_cost !== null && $node->planned_cost > 0 && $actualCostInput > 0) {
            $existingCost = (float) ActualCostTransaction::whereHas(
                'progressEntry',
                fn ($q) => $q->where('wbd_node_id', $node->id)
            )->where('status', '!=', 'REJECTED')->sum('amount');

            $remainingCostPlan = (float) $node->planned_cost - $existingCost;

            if ($actualCostInput > $remainingCostPlan) {
                $isOverCost = true;
            }
        }

        return DB::transaction(function () use (
            $project, $node, $data, $enteredBy,
            $isOverVolume, $existingVolume, $isOverCost, $existingCost, $actualCostInput
        ) {
            // Jika volume atau biaya melebihi rencana → wajib persetujuan Direktur,
            // tidak peduli role penginput.
            if ($isOverVolume || $isOverCost) {
                $status = ProgressStatus::PENDING_DIRECTOR_APPROVAL->value;
            } elseif ($enteredBy->isProjectManager()) {
                $status = ProgressStatus::AUTO_APPROVED->value;
            } else {
                $status = ProgressStatus::PENDING_PM_APPROVAL->value;
            }

            $progress = ProgressEntry::create([
                'project_id' => $project->id,
                'wbd_node_id' => $node->id,
                'progress_date' => $data['progress_date'],
                'progress_volume' => $data['progress_volume'],
                'note' => $data['note'] ?? null,
                'entered_by' => $enteredBy->id,
                'status' => $status,
            ]);

            if ($status === ProgressStatus::AUTO_APPROVED->value) {
                $progress->update([
                    'approved_by' => $enteredBy->id,
                    'approved_at' => now(),
                ]);
            }

            // Create cost transaction if actual_cost is provided
            $actualCost = $data['actual_cost'] ?? null;
            if ($actualCost !== null && $actualCost > 0) {
                ActualCostTransaction::create([
                    'project_id' => $project->id,
                    'progress_entry_id' => $progress->id,
                    'amount' => $actualCost,
                    'transaction_date' => $data['progress_date'],
                    'description' => $data['note'] ?? null,
                    'entered_by' => $enteredBy->id,
                    'status' => 'APPROVED',
                ]);
            }

            // Store attachment if provided
            if (!empty($data['attachment'])) {
                $file = $data['attachment'];
                $path = $file->store(BucketFolder . '/progress-attachments/' . $project->id, 's3');
                $progress->update(['attachment_path' => $path]);
            }

            // Handle remaining_volume: pakai nilai dari user, atau hitung otomatis
            $remainingVolume = isset($data['remaining_volume']) && $data['remaining_volume'] !== ''
                ? (float) $data['remaining_volume']
                : ($node->volume !== null
                    ? max(0, (float) $node->volume - (float) $data['progress_volume'])
                    : null);

            $progress->update(['remaining_volume' => $remainingVolume]);

            // Handle remaining_cost: pakai nilai dari user, atau hitung otomatis (remaining_volume × rate)
            $remainingCost = isset($data['remaining_cost']) && $data['remaining_cost'] !== ''
                ? (float) $data['remaining_cost']
                : ($remainingVolume !== null && $node->rate !== null
                    ? round($remainingVolume * (float) $node->rate, 2)
                    : null);

            $progress->update(['remaining_cost' => $remainingCost]);

            // Susun baris alasan overrun (dipakai bersama untuk notifikasi in-app & WhatsApp)
            $reasonLines = [];
            if ($isOverVolume) {
                $reasonLines[] = sprintf(
                    '- Volume: %s %s (sisa rencana sebelum entri ini: %s %s, rencana total: %s %s)',
                    number_format((float) $data['progress_volume'], 2, ',', '.'),
                    $node->unit,
                    number_format(max(0, (float) $node->volume - $existingVolume), 2, ',', '.'),
                    $node->unit,
                    number_format((float) $node->volume, 2, ',', '.'),
                    $node->unit,
                );
            }
            if ($isOverCost) {
                $reasonLines[] = sprintf(
                    '- Biaya: Rp %s (sisa rencana sebelum entri ini: Rp %s, rencana total: Rp %s)',
                    number_format($actualCostInput, 0, ',', '.'),
                    number_format(max(0, (float) $node->planned_cost - $existingCost), 0, ',', '.'),
                    number_format((float) $node->planned_cost, 0, ',', '.'),
                );
            }

            // Kirim notifikasi ke Direktur jika volume dan/atau biaya melebihi rencana
            if ($isOverVolume || $isOverCost) {
                $directors = User::whereHas(
                    'role',
                    fn($q) =>
                    $q->where('role_name', RoleName::DIREKSI->value)
                )->get();

                $notifMessage = sprintf(
                    '%s mencatat realisasi melebihi rencana untuk item "%s" pada proyek "%s".' . "\n" . '%s',
                    $enteredBy->full_name,
                    $node->name,
                    $project->project_name,
                    implode("\n", $reasonLines)
                );

                foreach ($directors as $director) {
                    Notification::create([
                        'user_id' => $director->id,
                        'triggered_by' => $enteredBy->id,
                        'type' => 'OVER_BUDGET_RISK',
                        'title' => 'Potensi Over Budget: ' . $node->name,
                        'message' => $notifMessage,
                        'data' => [
                            'project_id' => $project->id,
                            'project_name' => $project->project_name,
                            'wbd_node_id' => $node->id,
                            'wbd_node_name' => $node->name,
                            'progress_entry_id' => $progress->id,
                            'is_over_volume' => $isOverVolume,
                            'is_over_cost' => $isOverCost,
                            'volume_plan' => (float) $node->volume,
                            'volume_actual' => (float) $data['progress_volume'],
                            'cost_plan' => (float) $node->planned_cost,
                            'cost_actual' => $actualCostInput,
                        ],
                    ]);
                }
            }

            $this->auditLog->logCreate('progress_entry', $progress->id, [
                'status' => $status,
                'entered_by_role' => $enteredBy->role->role_name,
            ]);

            // WhatsApp: beritahu approver yang relevan
            if ($status === ProgressStatus::PENDING_PM_APPROVAL->value) {
                $msg = "*Notifikasi Aplikasi PMO - {$project->project_name}*\nAda progress baru dari {$enteredBy->full_name} pada proyek {$project->project_name} menunggu persetujuan Anda";
                User::whereHas('role', fn ($q) => $q->where('role_name', RoleName::PROJECT_MANAGER->value))
                    ->whereNotNull('phone')->where('phone', '!=', '')
                    ->each(fn ($u) => $this->whatsApp->send($u->phone, $msg));
            } elseif ($status === ProgressStatus::PENDING_DIRECTOR_APPROVAL->value) {
                $msg = "*Notifikasi Aplikasi PMO - {$project->project_name}*\n"
                    . "⚠️ Progress {$node->code} - {$node->name} menunggu persetujuan Anda karena melebihi rencana (overbudget)\n\n"
                    . "Diinput oleh: {$enteredBy->full_name}\n"
                    . implode("\n", $reasonLines) . "\n"
                    . "Catatan: " . (($data['note'] ?? '') !== '' ? $data['note'] : '-');
                User::whereHas('role', fn ($q) => $q->where('role_name', RoleName::DIREKSI->value))
                    ->whereNotNull('phone')->where('phone', '!=', '')
                    ->each(fn ($u) => $this->whatsApp->send($u->phone, $msg));
            }

            return $progress->fresh([
                'project',
                'wbdNode',
                'enteredByUser.role',
                'approvedByUser',
                'actualCostTransactions',
            ]);
        });
    }

    /**
     * Approve a pending progress entry.
     * - PENDING_PM_APPROVAL → hanya Manajer Kebun
     * - PENDING_DIRECTOR_APPROVAL → hanya Direktur
     */
    public function approveProgress(ProgressEntry $progress, User $approvedBy): ProgressEntry
    {
        if ($progress->status === ProgressStatus::PENDING_DIRECTOR_APPROVAL->value) {
            if (!$approvedBy->isDireksi()) {
                throw new \RuntimeException('Hanya Direktur yang dapat menyetujui progress yang melebihi volume rencana.');
            }
        } elseif ($progress->status === ProgressStatus::PENDING_PM_APPROVAL->value) {
            if (!$approvedBy->canApproveProgress()) {
                throw new \RuntimeException('Only ' . RoleName::PROJECT_MANAGER->value . ' can approve progress.');
            }
        } else {
            throw new \RuntimeException('Progress tidak dalam status yang dapat disetujui.');
        }

        $prevStatus = $progress->status;

        return DB::transaction(function () use ($progress, $approvedBy, $prevStatus) {
            $progress->update([
                'status' => ProgressStatus::APPROVED->value,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
            ]);

            $this->auditLog->logApprove('progress_entry', $progress->id);

            $fresh = $progress->fresh(['wbdNode', 'project', 'enteredByUser']);
            $nodeName = $fresh->wbdNode->name ?? '-';
            $projectName = $fresh->project->project_name ?? '-';
            $approverLabel = $prevStatus === ProgressStatus::PENDING_DIRECTOR_APPROVAL->value
                ? 'Direksi'
                : 'Manajer Kebun';
            $submitter = $fresh->enteredByUser;
            if ($submitter?->phone) {
                $this->whatsApp->send(
                    $submitter->phone,
                    "*Notifikasi Aplikasi PMO - {$projectName}*\nProgress {$nodeName} pada proyek {$projectName} telah disetujui {$approverLabel}"
                );
            }

            return $fresh;
        });
    }

    /**
     * Reject a pending progress entry.
     * - PENDING_PM_APPROVAL → hanya Manajer Kebun
     * - PENDING_DIRECTOR_APPROVAL → hanya Direktur
     */
    public function rejectProgress(ProgressEntry $progress, User $rejectedBy, string $reason): ProgressEntry
    {
        if ($progress->status === ProgressStatus::PENDING_DIRECTOR_APPROVAL->value) {
            if (!$rejectedBy->isDireksi()) {
                throw new \RuntimeException('Hanya Direktur yang dapat menolak progress yang melebihi volume rencana.');
            }
        } elseif ($progress->status === ProgressStatus::PENDING_PM_APPROVAL->value) {
            if (!$rejectedBy->canApproveProgress()) {
                throw new \RuntimeException('Only ' . RoleName::PROJECT_MANAGER->value . ' can reject progress.');
            }
        } else {
            throw new \RuntimeException('Progress tidak dalam status yang dapat ditolak.');
        }

        $prevStatus = $progress->status;

        return DB::transaction(function () use ($progress, $rejectedBy, $reason, $prevStatus) {
            $progress->update([
                'status' => ProgressStatus::REJECTED->value,
                'rejected_by' => $rejectedBy->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditLog->logReject('progress_entry', $progress->id, $reason);

            $fresh = $progress->fresh(['wbdNode', 'project', 'enteredByUser']);
            $nodeName = $fresh->wbdNode->name ?? '-';
            $projectName = $fresh->project->project_name ?? '-';
            $approverLabel = $prevStatus === ProgressStatus::PENDING_DIRECTOR_APPROVAL->value
                ? 'Direksi'
                : 'Manajer Kebun';
            $submitter = $fresh->enteredByUser;
            if ($submitter?->phone) {
                $this->whatsApp->send(
                    $submitter->phone,
                    "*Notifikasi Aplikasi PMO - {$projectName}*\nProgress {$nodeName} pada proyek {$projectName} ditolak {$approverLabel}. Alasan: {$reason}"
                );
            }

            return $fresh;
        });
    }
}
