<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\WbdVersionStatus;
use App\Models\Project;
use App\Models\ProgressEntry;
use App\Models\User;
use App\Models\WbdNode;
use App\Models\WbdRevisionDecision;
use App\Models\WbdVersion;
use Illuminate\Support\Facades\DB;

class WbdService
{
    public function __construct(
        private AuditLogService $auditLog,
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * Create a new DRAFT WBD version for a project.
     * If basedOnVersionId is provided, copies all nodes from that version.
     */
    public function createDraftVersion(Project $project, ?string $basedOnVersionId, string $createdBy): WbdVersion
    {
        return DB::transaction(function () use ($project, $basedOnVersionId, $createdBy) {
            $nextVersionNumber = ($project->wbdVersions()->max('version_number') ?? 0) + 1;

            $version = WbdVersion::create([
                'project_id' => $project->id,
                'version_number' => $nextVersionNumber,
                'status' => WbdVersionStatus::DRAFT->value,
                'based_on_version_id' => $basedOnVersionId,
                'is_active' => false,
            ]);

            if ($basedOnVersionId) {
                $this->copyNodesFromVersion($basedOnVersionId, $version->id);
            }

            $this->auditLog->logCreate('wbd_version', $version->id, $version->toArray());

            return $version;
        });
    }

    /**
     * Submit a WBD version for Director approval.
     */
    public function submitForApproval(WbdVersion $version, string $submittedBy): WbdVersion
    {
        if (!$version->isDraft()) {
            throw new \RuntimeException('Only DRAFT versions can be submitted for approval.');
        }

        return DB::transaction(function () use ($version, $submittedBy) {
            $old = $version->toArray();
            $version->update([
                'status' => WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value,
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
            ]);

            $this->auditLog->logSubmit('wbd_version', $version->id);

            $fresh = $version->fresh(['project']);
            $projectName = $fresh->project->project_name ?? '-';
            $versionNumber = $fresh->version_number;
            $message = "*Notifikasi Aplikasi PMO - {$projectName}*\nWBD versi {$versionNumber} menunggu persetujuan Anda";

            User::whereHas('role', fn ($q) => $q->where('role_name', RoleName::DIREKSI->value))
                ->whereNotNull('phone')->where('phone', '!=', '')
                ->each(fn ($u) => $this->whatsApp->send($u->phone, $message));

            return $fresh;
        });
    }

    /**
     * Approve a WBD version (Direksi only).
     * Sets the version as the active baseline for the project.
     * Previous active version becomes SUPERSEDED.
     */
    public function approveVersion(WbdVersion $version, string $approvedBy): WbdVersion
    {
        if (!$version->isPendingApproval()) {
            throw new \RuntimeException('Only PENDING_DIRECTOR_APPROVAL versions can be approved.');
        }

        return DB::transaction(function () use ($version, $approvedBy) {
            // Supersede previous active baseline
            WbdVersion::where('project_id', $version->project_id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'status' => WbdVersionStatus::SUPERSEDED->value,
                ]);

            $version->update([
                'status' => WbdVersionStatus::FINAL_APPROVED->value,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'is_active' => true,
            ]);

            // Update project active baseline
            Project::where('id', $version->project_id)->update([
                'active_wbd_version_id' => $version->id,
            ]);

            $this->auditLog->logApprove('wbd_version', $version->id);

            $fresh = $version->fresh(['project', 'submittedByUser']);
            $projectName = $fresh->project->project_name ?? '-';
            $versionNumber = $fresh->version_number;
            $message = "*Notifikasi Aplikasi PMO - {$projectName}*\nWBD versi {$versionNumber} telah disetujui Direksi";

            User::whereHas('role', fn ($q) => $q->whereIn('role_name', [
                    RoleName::PROJECT_MANAGER->value,
                    RoleName::ADMIN_PROYEK->value,
                ]))
                ->whereNotNull('phone')->where('phone', '!=', '')
                ->each(fn ($u) => $this->whatsApp->send($u->phone, $message));

            return $fresh;
        });
    }

    /**
     * Reject a WBD version (Direksi only).
     */
    public function rejectVersion(WbdVersion $version, string $rejectedBy, string $reason): WbdVersion
    {
        if (!$version->isPendingApproval()) {
            throw new \RuntimeException('Only PENDING_DIRECTOR_APPROVAL versions can be rejected.');
        }

        return DB::transaction(function () use ($version, $rejectedBy, $reason) {
            $version->update([
                'status' => WbdVersionStatus::REJECTED->value,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditLog->logReject('wbd_version', $version->id, $reason);

            $fresh = $version->fresh(['project', 'submittedByUser']);
            $projectName = $fresh->project->project_name ?? '-';
            $versionNumber = $fresh->version_number;
            $message = "*Notifikasi Aplikasi PMO - {$projectName}*\nWBD versi {$versionNumber} ditolak Direksi. Alasan: {$reason}";

            User::whereHas('role', fn ($q) => $q->whereIn('role_name', [
                    RoleName::PROJECT_MANAGER->value,
                    RoleName::ADMIN_PROYEK->value,
                ]))
                ->whereNotNull('phone')->where('phone', '!=', '')
                ->each(fn ($u) => $this->whatsApp->send($u->phone, $message));

            return $fresh;
        });
    }

    /**
     * Recalculate planned_cost and percent for all nodes in a version.
     */
    public function recalculate(WbdVersion $version): void
    {
        $allNodes = $version->nodes()->get()->keyBy('id');
        $totalProjectCost = 0;

        // Bottom-up: calculate group planned_cost from children
        $rootNodes = $allNodes->filter(fn ($n) => $n->parent_node_id === null);

        foreach ($rootNodes as $root) {
            $rootCost = $this->recalculateNodeCost($root, $allNodes);
            $totalProjectCost += $rootCost;
        }

        // Calculate total_percent for each node
        if ($totalProjectCost > 0) {
            foreach ($allNodes as $node) {
                $totalPercent = ($node->planned_cost / $totalProjectCost) * 100;
                $node->update(['total_percent' => round($totalPercent, 4)]);
            }
        }

        // Calculate component_percent (against parent)
        foreach ($allNodes as $node) {
            if ($node->parent_node_id) {
                $parent = $allNodes[$node->parent_node_id] ?? null;
                if ($parent && $parent->planned_cost > 0) {
                    $componentPercent = ($node->planned_cost / $parent->planned_cost) * 100;
                    $node->update(['component_percent' => round($componentPercent, 4)]);
                }
            }
        }
    }

    private function recalculateNodeCost(WbdNode $node, $allNodes): float
    {
        if ($node->isItem()) {
            $cost = round(($node->volume ?? 0) * ($node->rate ?? 0), 2);
            $node->update(['planned_cost' => $cost]);
            return $cost;
        }

        // GROUP: sum children
        $children = $allNodes->filter(fn ($n) => $n->parent_node_id === $node->id);
        $groupCost = 0;
        foreach ($children as $child) {
            $groupCost += $this->recalculateNodeCost($child, $allNodes);
        }

        $node->update(['planned_cost' => $groupCost]);
        return $groupCost;
    }

    // ─── Revisi Baseline In-Place ──────────────────────────────────────────

    /**
     * Direksi membuka akses bagi PM/Admin Proyek untuk mulai merevisi baseline aktif.
     */
    public function unlockBaselineRevision(WbdVersion $baseline, string $direksiId): WbdVersion
    {
        if (!$baseline->is_active) {
            throw new \RuntimeException('Hanya baseline aktif yang bisa dibuka untuk revisi.');
        }

        $baseline->update([
            'revision_unlocked_by' => $direksiId,
            'revision_unlocked_at' => now(),
        ]);

        $this->auditLog->logUpdate('wbd_version', $baseline->id, [], ['revision_unlocked_by' => $direksiId]);

        $fresh = $baseline->fresh(['project']);
        $projectName = $fresh->project->project_name ?? '-';
        $message = "*Notifikasi Aplikasi PMO - {$projectName}*\n"
            . "Direksi membuka akses revisi baseline WBD (v{$fresh->version_number}). Anda bisa memulai revisi.";

        User::whereHas('role', fn ($q) => $q->whereIn('role_name', [
                RoleName::PROJECT_MANAGER->value,
                RoleName::ADMIN_PROYEK->value,
            ]))
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->each(fn ($u) => $this->whatsApp->send($u->phone, $message));

        return $fresh;
    }

    /**
     * Direksi mencabut akses revisi sebelum PM/Admin Proyek mulai (belum ada draf revisi).
     */
    public function revokeBaselineUnlock(WbdVersion $baseline): WbdVersion
    {
        if (!$baseline->isRevisionUnlocked()) {
            throw new \RuntimeException('Baseline ini belum dibuka untuk revisi.');
        }

        $hasOpenRevision = $baseline->revisions()
            ->whereIn('status', [WbdVersionStatus::DRAFT->value, WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value])
            ->exists();

        if ($hasOpenRevision) {
            throw new \RuntimeException('Sudah ada revisi yang sedang berjalan untuk baseline ini — tolak revisi itu untuk membatalkan, bukan cabut akses.');
        }

        $baseline->update([
            'revision_unlocked_by' => null,
            'revision_unlocked_at' => null,
        ]);

        return $baseline->fresh();
    }

    /**
     * PM/Admin Proyek mulai revisi baseline setelah Direksi membuka akses — membuat WbdVersion
     * baru (DRAFT, is_baseline_revision=true) hasil clone node dari baseline, lalu consume unlock.
     */
    public function startBaselineRevision(WbdVersion $baseline, string $startedBy): WbdVersion
    {
        if (!$baseline->is_active) {
            throw new \RuntimeException('Hanya baseline aktif yang bisa direvisi.');
        }
        if (!$baseline->isRevisionUnlocked()) {
            throw new \RuntimeException('Direksi belum membuka akses revisi untuk baseline ini.');
        }

        $hasOpenRevision = $baseline->revisions()
            ->whereIn('status', [WbdVersionStatus::DRAFT->value, WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value])
            ->exists();
        if ($hasOpenRevision) {
            throw new \RuntimeException('Sudah ada revisi baseline ini yang masih berjalan.');
        }

        return DB::transaction(function () use ($baseline, $startedBy) {
            $nextVersionNumber = ($baseline->project->wbdVersions()->max('version_number') ?? 0) + 1;

            $revision = WbdVersion::create([
                'project_id' => $baseline->project_id,
                'version_number' => $nextVersionNumber,
                'status' => WbdVersionStatus::DRAFT->value,
                'based_on_version_id' => $baseline->id,
                'is_active' => false,
                'is_baseline_revision' => true,
            ]);

            $this->copyNodesFromVersion($baseline->id, $revision->id);

            $baseline->update([
                'revision_unlocked_by' => null,
                'revision_unlocked_at' => null,
            ]);

            $this->auditLog->logCreate('wbd_version', $revision->id, $revision->toArray());

            return $revision;
        });
    }

    /**
     * Hitung diff node-by-node (dicocokkan via `code`) antara revisi dan baseline yang direvisi.
     * Dipakai bersama oleh endpoint diff read-only dan finalizeBaselineRevision() — satu sumber
     * kebenaran supaya yang dilihat Direksi = yang benar-benar terjadi setelah diputuskan.
     */
    public function diffRevisionAgainstBaseline(WbdVersion $revision): array
    {
        $baseline = $revision->basedOnVersion;
        if (!$baseline) {
            throw new \RuntimeException('Revisi ini tidak terhubung ke baseline mana pun.');
        }

        $baselineNodes = WbdNode::where('wbd_version_id', $baseline->id)->get()->keyBy('code');
        $revisionNodes = WbdNode::where('wbd_version_id', $revision->id)->get()->keyBy('code');

        $modified = [];
        $added = [];
        $removed = [];
        $removedBlocked = [];

        foreach ($revisionNodes as $code => $revNode) {
            $baseNode = $baselineNodes->get($code);

            if (!$baseNode) {
                $added[] = [
                    'code' => $revNode->code,
                    'name' => $revNode->name,
                    'node_type' => $revNode->node_type,
                    'unit' => $revNode->unit,
                    'volume' => $revNode->volume !== null ? (float) $revNode->volume : null,
                    'planned_cost' => $revNode->planned_cost !== null ? (float) $revNode->planned_cost : null,
                ];
                continue;
            }

            // GROUP: cost/volume adalah rollup dari children, bukan field yang diedit langsung —
            // jangan tampilkan sebagai "perubahan" tersendiri yang perlu diputuskan Direksi.
            $fields = $revNode->node_type === 'GROUP'
                ? ['name', 'description', 'sort_order']
                : ['name', 'description', 'unit', 'volume', 'rate', 'planned_cost', 'start_date', 'duration_days', 'end_date', 'sort_order'];

            $changes = [];
            foreach ($fields as $f) {
                $oldVal = $baseNode->{$f};
                $newVal = $revNode->{$f};
                $oldCmp = $oldVal instanceof \Carbon\Carbon ? $oldVal->toDateString() : $oldVal;
                $newCmp = $newVal instanceof \Carbon\Carbon ? $newVal->toDateString() : $newVal;
                if ((string) $oldCmp !== (string) $newCmp) {
                    $changes[$f] = ['before' => $oldCmp, 'after' => $newCmp];
                }
            }

            if (empty($changes)) {
                continue;
            }

            $entry = [
                'code' => $code,
                'name' => $revNode->name,
                'changes' => $changes,
            ];

            $latestApproved = ProgressEntry::where('wbd_node_id', $baseNode->id)
                ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
                ->orderByDesc('progress_date')
                ->orderByDesc('created_at')
                ->first();

            if ($latestApproved && ($latestApproved->remaining_volume !== null || $latestApproved->remaining_cost !== null)) {
                $deltaVolume = (float) ($revNode->volume ?? 0) - (float) ($baseNode->volume ?? 0);
                $deltaCost = (float) ($revNode->planned_cost ?? 0) - (float) ($baseNode->planned_cost ?? 0);

                $oldRemVol = $latestApproved->remaining_volume !== null ? (float) $latestApproved->remaining_volume : null;
                $oldRemCost = $latestApproved->remaining_cost !== null ? (float) $latestApproved->remaining_cost : null;

                $newRemVol = $oldRemVol !== null ? $oldRemVol + $deltaVolume : null;
                $newRemCost = $oldRemCost !== null ? $oldRemCost + $deltaCost : null;

                $statusBefore = [];
                $statusAfter = [];
                if ($oldRemVol !== null) {
                    $statusBefore[] = $oldRemVol <= 0 ? 'Selesai' : 'Berjalan';
                    $statusAfter[] = $newRemVol <= 0 ? 'Selesai' : 'Berjalan';
                }
                if ($oldRemCost !== null) {
                    $statusBefore[] = $oldRemCost < 0 ? 'Over Budget' : 'On-Budget';
                    $statusAfter[] = $newRemCost < 0 ? 'Over Budget' : 'On-Budget';
                }

                if ($statusBefore !== $statusAfter) {
                    $entry['status_impact'] = [
                        'progress_entry_id' => $latestApproved->id,
                        'status_before' => implode(' / ', $statusBefore),
                        'status_after' => implode(' / ', $statusAfter),
                        'new_remaining_volume' => $newRemVol,
                        'new_remaining_cost' => $newRemCost,
                    ];
                }
            }

            $modified[] = $entry;
        }

        foreach ($baselineNodes as $code => $baseNode) {
            if ($revisionNodes->has($code)) {
                continue;
            }

            $item = [
                'code' => $code,
                'name' => $baseNode->name,
                'volume' => $baseNode->volume !== null ? (float) $baseNode->volume : null,
                'planned_cost' => $baseNode->planned_cost !== null ? (float) $baseNode->planned_cost : null,
            ];

            if (ProgressEntry::where('wbd_node_id', $baseNode->id)->exists()) {
                $removedBlocked[] = $item;
            } else {
                $removed[] = $item;
            }
        }

        return [
            'modified' => $modified,
            'added' => $added,
            'removed' => $removed,
            'removed_blocked' => $removedBlocked,
        ];
    }

    /**
     * Direksi memutuskan revisi baseline per-item (approve/reject masing-masing), lalu langsung
     * diterapkan ke baseline (untuk yang APPROVED) dalam satu transaksi. Item REJECTED atau
     * removed_blocked tidak mengubah apa pun di baseline.
     *
     * @param array $decisions [{code, decision: 'APPROVED'|'REJECTED', reason?}]
     */
    public function finalizeBaselineRevision(WbdVersion $revision, array $decisions, string $decidedBy): WbdVersion
    {
        if (!$revision->isBaselineRevision() || !$revision->isPendingApproval()) {
            throw new \RuntimeException('Hanya revisi baseline dengan status menunggu persetujuan yang bisa diputuskan.');
        }

        return DB::transaction(function () use ($revision, $decisions, $decidedBy) {
            $diff = $this->diffRevisionAgainstBaseline($revision);

            $decidableCodes = collect($diff['modified'])->pluck('code')
                ->merge(collect($diff['added'])->pluck('code'))
                ->merge(collect($diff['removed'])->pluck('code'))
                ->unique()->sort()->values()->all();

            $decisionMap = collect($decisions)->keyBy('code');
            $submittedCodes = $decisionMap->keys()->sort()->values()->all();

            if ($submittedCodes !== $decidableCodes) {
                throw new \RuntimeException('Diff sudah berubah atau keputusan tidak lengkap — muat ulang halaman dan coba lagi.');
            }

            $baseline = $revision->basedOnVersion;
            $baselineNodesByCode = WbdNode::where('wbd_version_id', $baseline->id)->get()->keyBy('code');
            $revisionNodesByCode = WbdNode::where('wbd_version_id', $revision->id)->get()->keyBy('code');

            $approvedCount = 0;
            $rejectedCount = 0;
            $approvedCodes = [];
            $rejectedSummary = [];

            foreach ($diff['modified'] as $item) {
                $code = $item['code'];
                $decision = $decisionMap[$code];
                $this->recordRevisionDecision($revision, $code, 'MODIFIED', $decision, $decidedBy);

                if ($decision['decision'] === 'APPROVED') {
                    $baseNode = $baselineNodesByCode[$code];
                    $revNode = $revisionNodesByCode[$code];
                    $baseNode->update([
                        'name' => $revNode->name,
                        'description' => $revNode->description,
                        'unit' => $revNode->unit,
                        'volume' => $revNode->volume,
                        'rate' => $revNode->rate,
                        'planned_cost' => $revNode->planned_cost,
                        'start_date' => $revNode->start_date,
                        'duration_days' => $revNode->duration_days,
                        'end_date' => $revNode->end_date,
                        'sort_order' => $revNode->sort_order,
                    ]);

                    if (!empty($item['status_impact'])) {
                        ProgressEntry::find($item['status_impact']['progress_entry_id'])?->update([
                            'remaining_volume' => $item['status_impact']['new_remaining_volume'],
                            'remaining_cost' => $item['status_impact']['new_remaining_cost'],
                        ]);
                    }

                    $approvedCount++;
                    $approvedCodes[] = $code;
                } else {
                    $rejectedCount++;
                    $rejectedSummary[] = $code . (!empty($decision['reason']) ? " — Alasan: \"{$decision['reason']}\"" : '');
                }
            }

            foreach ($diff['added'] as $item) {
                $code = $item['code'];
                $decision = $decisionMap[$code];
                $this->recordRevisionDecision($revision, $code, 'ADDED', $decision, $decidedBy);

                if ($decision['decision'] === 'APPROVED') {
                    $revNode = $revisionNodesByCode[$code];
                    $parentNode = $revNode->parent_node_id
                        ? $revisionNodesByCode->first(fn ($n) => $n->id === $revNode->parent_node_id)
                        : null;
                    $parentId = $parentNode ? ($baselineNodesByCode[$parentNode->code]->id ?? null) : null;

                    $newNode = WbdNode::create([
                        'wbd_version_id' => $baseline->id,
                        'parent_node_id' => $parentId,
                        'node_type' => $revNode->node_type,
                        'code' => $revNode->code,
                        'name' => $revNode->name,
                        'description' => $revNode->description,
                        'unit' => $revNode->unit,
                        'volume' => $revNode->volume,
                        'rate' => $revNode->rate,
                        'planned_cost' => $revNode->planned_cost,
                        'start_date' => $revNode->start_date,
                        'duration_days' => $revNode->duration_days,
                        'end_date' => $revNode->end_date,
                        'status' => $revNode->status,
                        'sort_order' => $revNode->sort_order,
                    ]);
                    $baselineNodesByCode[$code] = $newNode;

                    $approvedCount++;
                    $approvedCodes[] = $code;
                } else {
                    $rejectedCount++;
                    $rejectedSummary[] = $code . (!empty($decision['reason']) ? " — Alasan: \"{$decision['reason']}\"" : '');
                }
            }

            $idsToDelete = [];
            foreach ($diff['removed'] as $item) {
                $code = $item['code'];
                $decision = $decisionMap[$code];
                $this->recordRevisionDecision($revision, $code, 'REMOVED', $decision, $decidedBy);

                if ($decision['decision'] === 'APPROVED') {
                    $idsToDelete[] = $baselineNodesByCode[$code]->id;
                    $approvedCount++;
                    $approvedCodes[] = $code;
                } else {
                    $rejectedCount++;
                    $rejectedSummary[] = $code . (!empty($decision['reason']) ? " — Alasan: \"{$decision['reason']}\"" : '');
                }
            }

            // Hapus leaf-first: wbd_nodes.parent_node_id self-referencing FK menolak
            // penghapusan parent selagi anak (yang juga akan dihapus) masih ada.
            // Ulangi beberapa pass, tiap pass hapus node dalam set ini yang tidak lagi
            // menjadi parent dari node lain yang MASIH ada di database.
            $remaining = $idsToDelete;
            $pass = 0;
            while (!empty($remaining)) {
                $pass++;
                if ($pass > 50) {
                    throw new \RuntimeException('Gagal menghapus item WBD — struktur pohon terlalu dalam atau tidak konsisten.');
                }
                $stillReferenced = WbdNode::whereIn('parent_node_id', $remaining)->pluck('parent_node_id')->unique()->all();
                $deletable = array_values(array_diff($remaining, $stillReferenced));
                if (empty($deletable)) {
                    throw new \RuntimeException('Gagal menghapus item WBD — kemungkinan ada item anak yang tidak ikut dihapus.');
                }
                WbdNode::whereIn('id', $deletable)->delete();
                $remaining = array_values(array_diff($remaining, $deletable));
            }

            $this->recalculate($baseline);

            $finalStatus = $approvedCount > 0 ? WbdVersionStatus::MERGED->value : WbdVersionStatus::REJECTED->value;
            $revision->update([
                'status' => $finalStatus,
                'approved_by' => $decidedBy,
                'approved_at' => now(),
            ]);

            $this->auditLog->logApprove('wbd_version', $revision->id, "disetujui={$approvedCount}, ditolak={$rejectedCount}");

            $fresh = $revision->fresh(['project']);
            $projectName = $fresh->project->project_name ?? '-';
            $lines = ["*Notifikasi Aplikasi PMO - {$projectName}*", 'Revisi baseline WBD telah diputuskan Direksi:'];
            if ($approvedCount > 0) {
                $lines[] = "✅ Disetujui ({$approvedCount}): " . implode(', ', $approvedCodes);
            }
            if ($rejectedCount > 0) {
                $lines[] = "❌ Ditolak ({$rejectedCount}): " . implode('; ', $rejectedSummary);
            }
            $message = implode("\n", $lines);

            User::whereHas('role', fn ($q) => $q->whereIn('role_name', [
                    RoleName::PROJECT_MANAGER->value,
                    RoleName::ADMIN_PROYEK->value,
                ]))
                ->whereNotNull('phone')->where('phone', '!=', '')
                ->each(fn ($u) => $this->whatsApp->send($u->phone, $message));

            return $fresh;
        });
    }

    private function recordRevisionDecision(WbdVersion $revision, string $code, string $changeType, array $decision, string $decidedBy): void
    {
        WbdRevisionDecision::create([
            'wbd_version_id' => $revision->id,
            'node_code' => $code,
            'change_type' => $changeType,
            'decision' => $decision['decision'],
            'reason' => $decision['reason'] ?? null,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
        ]);
    }

    private function copyNodesFromVersion(string $sourceVersionId, string $targetVersionId): void
    {
        $sourceNodes = WbdNode::where('wbd_version_id', $sourceVersionId)
            ->orderBy('sort_order')
            ->get();

        // Map old IDs to new IDs for parent references
        $idMap = [];

        foreach ($sourceNodes as $node) {
            $newNode = $node->replicate();
            $newNode->wbd_version_id = $targetVersionId;
            $newNode->parent_node_id = null; // will fix below
            // Reset progress-related state — only plan structure is copied, not realisasi
            $newNode->status = 'ACTIVE';
            $newNode->save();
            $idMap[$node->id] = $newNode->id;
        }

        // Fix parent references
        foreach ($sourceNodes as $node) {
            if ($node->parent_node_id && isset($idMap[$node->parent_node_id])) {
                WbdNode::where('id', $idMap[$node->id])->update([
                    'parent_node_id' => $idMap[$node->parent_node_id],
                ]);
            }
        }
    }
}
