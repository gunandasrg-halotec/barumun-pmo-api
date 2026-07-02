<?php

namespace App\Services;

use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use App\Models\WbdVersion;

/**
 * Phase 10 — Integrity Service.
 *
 * Centralises all SRS business-rule integrity checks so they can be
 * called from controllers, commands, or tests without duplicating logic.
 *
 * These checks are DEFENSIVE — the same rules are already enforced in
 * their respective services (WbdService, ProgressService, CostService).
 * This service is used to VERIFY integrity, not to perform operations.
 */
class IntegrityService
{
    // ─── WBD Baseline ────────────────────────────────────────────────────────

    /**
     * Rule: Only one WBD version may be active (is_active = true) per project.
     */
    public function checkSingleActiveBaseline(string $projectId): array
    {
        $count = WbdVersion::where('project_id', $projectId)
            ->where('is_active', true)
            ->count();

        return [
            'check'   => 'single_active_baseline',
            'passed'  => $count <= 1,
            'details' => "Found {$count} active baseline(s) for project {$projectId}.",
        ];
    }

    /**
     * Rule: A project's active_wbd_version_id must match the WbdVersion marked is_active.
     */
    public function checkProjectBaselineConsistency(Project $project): array
    {
        $activeVersion = WbdVersion::where('project_id', $project->id)
            ->where('is_active', true)
            ->first();

        $consistent = $project->active_wbd_version_id === ($activeVersion?->id);

        return [
            'check'   => 'project_baseline_consistency',
            'passed'  => $consistent,
            'details' => $consistent
                ? 'Project active_wbd_version_id matches the active WBD version.'
                : "Mismatch: project.active_wbd_version_id={$project->active_wbd_version_id}, active WBD id=" . ($activeVersion?->id ?? 'none'),
        ];
    }

    // ─── Progress ─────────────────────────────────────────────────────────────

    /**
     * Rule: No progress entries may exist for a project without an active baseline.
     */
    public function checkProgressRequiresBaseline(string $projectId): array
    {
        $project = Project::find($projectId);
        $hasBaseline = $project?->hasActiveBaseline();

        if ($hasBaseline) {
            return ['check' => 'progress_requires_baseline', 'passed' => true, 'details' => 'Project has active baseline.'];
        }

        $orphanCount = ProgressEntry::where('project_id', $projectId)->count();

        return [
            'check'   => 'progress_requires_baseline',
            'passed'  => $orphanCount === 0,
            'details' => $orphanCount > 0
                ? "WARNING: {$orphanCount} progress entries exist but no active baseline."
                : 'No progress entries found and no baseline. OK.',
        ];
    }

    /**
     * Rule: Progress entries must only target ITEM-type WBD nodes.
     */
    public function checkProgressOnItemNodesOnly(string $projectId): array
    {
        $violations = ProgressEntry::where('project_id', $projectId)
            ->join('wbd_nodes', 'wbd_nodes.id', '=', 'progress_entries.wbd_node_id')
            ->where('wbd_nodes.node_type', '!=', 'ITEM')
            ->count('progress_entries.id');

        return [
            'check'   => 'progress_on_item_nodes_only',
            'passed'  => $violations === 0,
            'details' => $violations > 0
                ? "VIOLATION: {$violations} progress entries linked to non-ITEM nodes."
                : 'All progress entries are linked to ITEM nodes.',
        ];
    }

    // ─── Actual Cost ──────────────────────────────────────────────────────────

    /**
     * Rule: Every actual cost transaction must have a linked progress entry.
     */
    public function checkCostRequiresProgress(string $projectId): array
    {
        $orphanCount = ActualCostTransaction::where('project_id', $projectId)
            ->whereNull('progress_entry_id')
            ->count();

        return [
            'check'   => 'cost_requires_progress',
            'passed'  => $orphanCount === 0,
            'details' => $orphanCount > 0
                ? "VIOLATION: {$orphanCount} cost transactions without a linked progress entry."
                : 'All cost transactions have a linked progress entry.',
        ];
    }

    /**
     * Rule: Cost transaction's progress entry must belong to the same project.
     */
    public function checkCostProgressProjectMatch(string $projectId): array
    {
        $violations = ActualCostTransaction::where('actual_cost_transactions.project_id', $projectId)
            ->join('progress_entries', 'progress_entries.id', '=', 'actual_cost_transactions.progress_entry_id')
            ->where('progress_entries.project_id', '!=', $projectId)
            ->count();

        return [
            'check'   => 'cost_progress_project_match',
            'passed'  => $violations === 0,
            'details' => $violations > 0
                ? "VIOLATION: {$violations} cost transactions linked to progress from a different project."
                : 'All cost-to-progress project links are consistent.',
        ];
    }

    // ─── File ─────────────────────────────────────────────────────────────────

    /**
     * Rule: IMAGE files must have caption and photo_date.
     */
    public function checkImageMetadataCompleteness(string $projectId): array
    {
        $violations = \App\Models\ProjectFile::where('project_id', $projectId)
            ->where('file_type', 'IMAGE')
            ->where(function ($q) {
                $q->whereNull('caption')->orWhereNull('photo_date');
            })
            ->count();

        return [
            'check'   => 'image_metadata_completeness',
            'passed'  => $violations === 0,
            'details' => $violations > 0
                ? "VIOLATION: {$violations} IMAGE files missing caption or photo_date."
                : 'All IMAGE files have complete metadata.',
        ];
    }

    // ─── Full Project Integrity Report ────────────────────────────────────────

    /**
     * Run all integrity checks for a project and return a consolidated report.
     */
    public function runAllChecks(Project $project): array
    {
        $projectId = $project->id;

        $checks = [
            $this->checkSingleActiveBaseline($projectId),
            $this->checkProjectBaselineConsistency($project),
            $this->checkProgressRequiresBaseline($projectId),
            $this->checkProgressOnItemNodesOnly($projectId),
            $this->checkCostRequiresProgress($projectId),
            $this->checkCostProgressProjectMatch($projectId),
            $this->checkImageMetadataCompleteness($projectId),
        ];

        $allPassed = collect($checks)->every(fn ($c) => $c['passed']);

        return [
            'project_id'  => $projectId,
            'all_passed'  => $allPassed,
            'checked_at'  => now()->toIso8601String(),
            'checks'      => $checks,
        ];
    }
}
