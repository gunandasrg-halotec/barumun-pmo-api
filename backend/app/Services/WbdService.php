<?php

namespace App\Services;

use App\Enums\WbdVersionStatus;
use App\Models\Project;
use App\Models\WbdNode;
use App\Models\WbdVersion;
use Illuminate\Support\Facades\DB;

class WbdService
{
    public function __construct(private AuditLogService $auditLog) {}

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

            return $version->fresh();
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

            return $version->fresh();
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

            return $version->fresh();
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
            $cost = ($node->volume ?? 0) * ($node->rate ?? 0);
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
