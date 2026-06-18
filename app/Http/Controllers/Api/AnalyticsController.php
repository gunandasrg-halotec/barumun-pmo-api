<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

/**
 * Phase 8 — Dashboard and Analytics.
 *
 * SRS rule: ALL analytics endpoints ONLY use officially approved data:
 *   - Progress:    status IN ('APPROVED', 'AUTO_APPROVED')
 *   - Actual Cost: status = 'APPROVED'
 *   - Baseline:    active WBD version (is_active = true, FINAL_APPROVED)
 */
class AnalyticsController extends Controller
{
    // ─── GET /v1/projects/{project}/dashboard ────────────────────────────────

    #[OA\Get(
        tags: [ANALYTICS_TAG],
        path: "/v1/projects/{project}/dashboard",
        operationId: "AnalyticsController@dashboard",
        summary: "Project dashboard KPI summary. All data from approved transactions and active baseline only.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Dashboard KPI data")]
    #[ResponseDefault()]
    public function dashboard(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched',
                'data'    => [
                    'has_baseline' => false,
                    'message'      => 'No active baseline available. Please create and approve a WBD version.',
                ],
            ]);
        }

        $activeVersionId = $project->active_wbd_version_id;

        // Baseline cost — sum of root-level planned_cost from active WBD
        $totalBaselineCost = (float) WbdNode::where('wbd_version_id', $activeVersionId)
            ->whereNull('parent_node_id')
            ->sum('planned_cost');

        // Approved actual cost
        $totalApprovedCost = (float) ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->sum('amount');

        // Official progress — approved entries only
        $officialProgressCount = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->count();

        // Pending items
        $pendingProgressCount = ProgressEntry::where('project_id', $project->id)
            ->where('status', 'PENDING_PM_APPROVAL')
            ->count();

        $pendingCostCount = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'REVIEW')
            ->count();

        // Overall progress % — approved volume / planned volume across all ITEM nodes
        $plannedVolume  = (float) WbdNode::where('wbd_version_id', $activeVersionId)
            ->where('node_type', 'ITEM')
            ->sum('volume');

        $approvedVolume = (float) ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->sum('progress_volume');

        $overallProgressPercent = $plannedVolume > 0
            ? min(100, round(($approvedVolume / $plannedVolume) * 100, 2))
            : 0;

        $costDeviation = $totalApprovedCost - $totalBaselineCost;

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data fetched successfully',
            'data'    => [
                'has_baseline'                  => true,
                'active_baseline_version'       => $project->activeWbdVersion->version_number,
                'total_baseline_cost'           => $totalBaselineCost,
                'total_actual_cost_approved'    => $totalApprovedCost,
                'cost_deviation'                => $costDeviation,
                'cost_deviation_percent'        => $totalBaselineCost > 0
                    ? round(($costDeviation / $totalBaselineCost) * 100, 2)
                    : 0,
                'overall_progress_percent'      => $overallProgressPercent,
                'total_official_progress_count' => $officialProgressCount,
                'pending_progress_approval'     => $pendingProgressCount,
                'pending_cost_review'           => $pendingCostCount,
            ],
        ]);
    }

    // ─── GET /v1/projects/{project}/s-curve ──────────────────────────────────

    #[OA\Get(
        tags: [ANALYTICS_TAG],
        path: "/v1/projects/{project}/s-curve",
        operationId: "AnalyticsController@sCurve",
        summary: "S-Curve: monthly cumulative approved progress volume and approved cost. Approved data only.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "S-Curve series data")]
    #[ResponseDefault()]
    public function sCurve(Request $request, Project $project): JsonResponse
    {
        // Approved progress by month
        $progressByMonth = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->selectRaw("DATE_FORMAT(progress_date, '%Y-%m') as period, SUM(progress_volume) as volume")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Approved cost by month
        $costByMonth = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as period, SUM(amount) as amount")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Union of all periods
        $allPeriods = $progressByMonth->keys()
            ->merge($costByMonth->keys())
            ->unique()
            ->sort()
            ->values();

        $cumulativeVolume = 0.0;
        $cumulativeCost   = 0.0;
        $series           = [];

        foreach ($allPeriods as $period) {
            $cumulativeVolume += (float) ($progressByMonth[$period]->volume ?? 0);
            $cumulativeCost   += (float) ($costByMonth[$period]->amount ?? 0);

            $series[] = [
                'period'            => $period,
                'volume'            => (float) ($progressByMonth[$period]->volume ?? 0),
                'cost'              => (float) ($costByMonth[$period]->amount ?? 0),
                'cumulative_volume' => round($cumulativeVolume, 4),
                'cumulative_cost'   => round($cumulativeCost, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'S-Curve data fetched successfully',
            'data'    => [
                'actual_series' => $series,
            ],
        ]);
    }

    // ─── GET /v1/projects/{project}/cost-analysis ────────────────────────────

    #[OA\Get(
        tags: [ANALYTICS_TAG],
        path: "/v1/projects/{project}/cost-analysis",
        operationId: "AnalyticsController@costAnalysis",
        summary: "Cost analysis: baseline vs approved actual cost per root WBD node group. Approved data only.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Cost analysis per WBD root node")]
    #[ResponseDefault()]
    public function costAnalysis(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'Cost analysis fetched',
                'data'    => [],
                'meta'    => ['has_baseline' => false],
            ]);
        }

        $activeVersionId = $project->active_wbd_version_id;

        // All nodes (flat) for the active version
        $nodes = WbdNode::where('wbd_version_id', $activeVersionId)
            ->orderBy('sort_order')
            ->get();

        // Approved actual cost grouped by WBD node (via progress entries)
        $actualCostByNode = DB::table('actual_cost_transactions as act')
            ->join('progress_entries as pe', 'pe.id', '=', 'act.progress_entry_id')
            ->where('act.project_id', $project->id)
            ->where('act.status', 'APPROVED')
            ->selectRaw('pe.wbd_node_id, SUM(act.amount) as total_actual')
            ->groupBy('pe.wbd_node_id')
            ->pluck('total_actual', 'wbd_node_id');

        $totalBaselineCost = (float) $nodes->whereNull('parent_node_id')->sum('planned_cost');

        $analysis = $nodes->map(function ($node) use ($actualCostByNode, $totalBaselineCost) {
            $baseline   = (float) $node->planned_cost;
            $actual     = (float) ($actualCostByNode[$node->id] ?? 0);
            $deviation  = $actual - $baseline;
            $weight     = $totalBaselineCost > 0 ? ($baseline / $totalBaselineCost) * 100 : 0;

            return [
                'id'                   => $node->id,
                'parent_node_id'       => $node->parent_node_id,
                'code'                 => $node->code,
                'name'                 => $node->name,
                'node_type'            => $node->node_type,
                'weight_percent'       => round($weight, 2),
                'baseline_cost'        => $baseline,
                'actual_cost_approved' => $actual,
                'deviation'            => $deviation,
                'deviation_percent'    => $baseline > 0
                    ? round(($deviation / $baseline) * 100, 2)
                    : 0,
                'is_over_budget'       => $deviation > 0,
            ];
        });

        $flatNodes = $analysis->map(function ($node) {
            return array_merge($node, [
                'planned_cost' => $node['baseline_cost'],
                'actual_cost'  => $node['actual_cost_approved'],
            ]);
        });

        $totalActualCost = $flatNodes->sum('actual_cost');

        return response()->json([
            'success' => true,
            'message' => 'Cost analysis fetched successfully',
            'data'    => [
                'items'   => $flatNodes->values(),
                'groups'  => $flatNodes->where('node_type', 'GROUP')->values(),
                'summary' => [
                    'total_baseline_cost' => $totalBaselineCost,
                    'total_actual_cost'   => $totalActualCost,
                ],
            ],
            'meta'    => [
                'has_baseline'     => true,
                'baseline_version' => $project->activeWbdVersion->version_number,
                'total_baseline'   => $totalBaselineCost,
            ],
        ]);
    }
}
