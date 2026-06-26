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

        // ── Realisasi totals ─────────────────────────────────────────────────────
        $approvedVolume = (float) ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->sum('progress_volume');

        // ── Planned progress % from baseline schedule (volume & cost) ─────────
        // Distribute each ITEM node's volume/cost linearly over start_date→end_date,
        // then cumulate up to today to get "how much should have been done by now".
        $today = \Carbon\Carbon::today();
        $todayPeriod = $today->format('Y-m');

        $itemNodes = WbdNode::where('wbd_version_id', $activeVersionId)
            ->where('node_type', 'ITEM')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();

        $totalPlannedVolume    = 0.0;
        $totalPlannedCost      = 0.0;
        $scheduledVolToDate    = 0.0;
        $scheduledCostToDate   = 0.0;

        foreach ($itemNodes as $node) {
            $start        = \Carbon\Carbon::parse($node->start_date)->startOfMonth();
            $end          = \Carbon\Carbon::parse($node->end_date)->startOfMonth();
            $months       = $start->diffInMonths($end) + 1;
            $volPerMonth  = $months > 0 ? ((float) $node->volume / $months) : 0;
            $costPerMonth = $months > 0 ? ((float) $node->planned_cost / $months) : 0;

            $totalPlannedVolume += (float) $node->volume;
            $totalPlannedCost   += (float) $node->planned_cost;

            $cur = $start->copy();
            for ($i = 0; $i < $months; $i++) {
                if ($cur->format('Y-m') <= $todayPeriod) {
                    $scheduledVolToDate  += $volPerMonth;
                    $scheduledCostToDate += $costPerMonth;
                }
                $cur->addMonth();
            }
        }

        // For nodes without schedule dates, add their full volume/cost to total
        // but not to scheduled (they have no timeline basis).
        $totalPlannedVolume += (float) WbdNode::where('wbd_version_id', $activeVersionId)
            ->where('node_type', 'ITEM')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereNull('end_date'))
            ->sum('volume');
        $totalPlannedCost += (float) WbdNode::where('wbd_version_id', $activeVersionId)
            ->where('node_type', 'ITEM')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereNull('end_date'))
            ->sum('planned_cost');

        // Planned % = scheduled-to-date / total planned
        $plannedProgressPercent = $totalPlannedVolume > 0
            ? min(100, round(($scheduledVolToDate / $totalPlannedVolume) * 100, 2))
            : 0;

        $plannedCostPercent = $totalPlannedCost > 0
            ? min(100, round(($scheduledCostToDate / $totalPlannedCost) * 100, 2))
            : 0;

        // ── Actual progress % (completion estimate) ───────────────────────────
        // actual volume: realisasi / (realisasi + sisa terbaru per node)
        $latestRemainingByNode = DB::table('progress_entries as pe')
            ->join(DB::raw('(
                SELECT wbd_node_id, MAX(progress_date) as max_date
                FROM progress_entries
                WHERE project_id = \'' . $project->id . '\'
                AND status IN (\'APPROVED\', \'AUTO_APPROVED\')
                GROUP BY wbd_node_id
            ) as latest'), function ($join) {
                $join->on('pe.wbd_node_id', '=', 'latest.wbd_node_id')
                     ->on('pe.progress_date', '=', 'latest.max_date');
            })
            ->where('pe.project_id', $project->id)
            ->whereIn('pe.status', ['APPROVED', 'AUTO_APPROVED'])
            ->whereNotNull('pe.remaining_volume')
            ->select('pe.wbd_node_id', 'pe.remaining_volume')
            ->get();

        $totalRemainingVolume  = $latestRemainingByNode->sum('remaining_volume');
        $volDenominator        = $approvedVolume + $totalRemainingVolume;
        $actualProgressPercent = $volDenominator > 0
            ? min(100, round(($approvedVolume / $volDenominator) * 100, 2))
            : 0;

        // actual cost: biaya_realisasi / (biaya_realisasi + sisa_biaya terbaru per node)
        $latestRemainingCostByNode = DB::table('progress_entries as pe')
            ->join(DB::raw('(
                SELECT wbd_node_id, MAX(progress_date) as max_date
                FROM progress_entries
                WHERE project_id = \'' . $project->id . '\'
                AND status IN (\'APPROVED\', \'AUTO_APPROVED\')
                GROUP BY wbd_node_id
            ) as latest'), function ($join) {
                $join->on('pe.wbd_node_id', '=', 'latest.wbd_node_id')
                     ->on('pe.progress_date', '=', 'latest.max_date');
            })
            ->where('pe.project_id', $project->id)
            ->whereIn('pe.status', ['APPROVED', 'AUTO_APPROVED'])
            ->whereNotNull('pe.remaining_cost')
            ->select('pe.wbd_node_id', 'pe.remaining_cost')
            ->get();

        $totalRemainingCost  = $latestRemainingCostByNode->sum('remaining_cost');
        $costDenominator     = $totalApprovedCost + $totalRemainingCost;
        $actualCostPercent   = $costDenominator > 0
            ? min(100, round(($totalApprovedCost / $costDenominator) * 100, 2))
            : 0;

        // Achievement rate (legacy): realisasi / total rencana
        $overallProgressPercent = $totalPlannedVolume > 0
            ? min(100, round(($approvedVolume / $totalPlannedVolume) * 100, 2))
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
                'planned_progress_percent'      => $plannedProgressPercent,
                'actual_progress_percent'       => $actualProgressPercent,
                'planned_cost_percent'          => $plannedCostPercent,
                'actual_cost_percent'           => $actualCostPercent,
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
        $project->load('activeWbdVersion');

        // ── Actual series ─────────────────────────────────────────────────────
        $progressByMonth = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->selectRaw("DATE_FORMAT(progress_date, '%Y-%m') as period, SUM(progress_volume) as volume")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $costByMonth = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as period, SUM(amount) as amount")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // ── Planned series from WBD baseline ──────────────────────────────────
        $planVolumeByMonth = [];
        $planCostByMonth   = [];
        $totalPlannedVolume = 0.0;

        if ($project->hasActiveBaseline()) {
            $itemNodes = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
                ->where('node_type', 'ITEM')
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->get();

            foreach ($itemNodes as $node) {
                $start        = \Carbon\Carbon::parse($node->start_date)->startOfMonth();
                $end          = \Carbon\Carbon::parse($node->end_date)->startOfMonth();
                $months       = $start->diffInMonths($end) + 1;
                $volPerMonth  = $months > 0 ? ((float) $node->volume / $months) : 0;
                $costPerMonth = $months > 0 ? ((float) $node->planned_cost / $months) : 0;
                $totalPlannedVolume += (float) $node->volume;

                $cur = $start->copy();
                for ($i = 0; $i < $months; $i++) {
                    $p = $cur->format('Y-m');
                    $planVolumeByMonth[$p] = ($planVolumeByMonth[$p] ?? 0) + $volPerMonth;
                    $planCostByMonth[$p]   = ($planCostByMonth[$p] ?? 0) + $costPerMonth;
                    $cur->addMonth();
                }
            }
            ksort($planVolumeByMonth);
            ksort($planCostByMonth);
        }

        // ── Union of all periods ──────────────────────────────────────────────
        $allPeriods = collect(array_merge(
            $progressByMonth->keys()->toArray(),
            $costByMonth->keys()->toArray(),
            array_keys($planVolumeByMonth)
        ))->unique()->sort()->values();

        $cumActualVol  = 0.0;
        $cumActualCost = 0.0;
        $cumPlanVol    = 0.0;
        $cumPlanCost   = 0.0;
        $volumeCurve   = [];
        $costCurve     = [];

        foreach ($allPeriods as $period) {
            $cumActualVol  += (float) ($progressByMonth[$period]->volume ?? 0);
            $cumActualCost += (float) ($costByMonth[$period]->amount ?? 0);
            $cumPlanVol    += (float) ($planVolumeByMonth[$period] ?? 0);
            $cumPlanCost   += (float) ($planCostByMonth[$period] ?? 0);

            $actualVolPct = $totalPlannedVolume > 0
                ? round(($cumActualVol / $totalPlannedVolume) * 100, 2)
                : 0;
            $planVolPct = $totalPlannedVolume > 0
                ? round(($cumPlanVol / $totalPlannedVolume) * 100, 2)
                : 0;

            $volumeCurve[] = [
                'period'            => $period,
                'plan_cumulative'   => $planVolPct,
                'actual_cumulative' => $actualVolPct,
            ];

            $costCurve[] = [
                'period'            => $period,
                'plan_cumulative'   => round($cumPlanCost, 2),
                'actual_cumulative' => round($cumActualCost, 2),
            ];
        }

        // ── Deviations: ITEM nodes with significant cost deviation ────────────
        $deviations = [];
        if ($project->hasActiveBaseline()) {
            $actualCostByNode = DB::table('actual_cost_transactions as act')
                ->join('progress_entries as pe', 'pe.id', '=', 'act.progress_entry_id')
                ->where('act.project_id', $project->id)
                ->where('act.status', 'APPROVED')
                ->selectRaw('pe.wbd_node_id, SUM(act.amount) as total_actual')
                ->groupBy('pe.wbd_node_id')
                ->pluck('total_actual', 'wbd_node_id');

            $deviations = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
                ->where('node_type', 'ITEM')
                ->get()
                ->map(function ($n) use ($actualCostByNode) {
                    $plan   = (float) $n->planned_cost;
                    $actual = (float) ($actualCostByNode[$n->id] ?? 0);
                    $dev    = $plan > 0 ? round((($actual - $plan) / $plan) * 100, 2) : 0;
                    return ['name' => $n->name, 'code' => $n->code, 'deviation_percent' => $dev];
                })
                ->filter(fn ($d) => abs($d['deviation_percent']) >= 5)
                ->sortByDesc(fn ($d) => abs($d['deviation_percent']))
                ->values()
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'message' => 'S-Curve data fetched successfully',
            'data'    => [
                'volume_curve' => $volumeCurve,
                'cost_curve'   => $costCurve,
                'insights'     => [],
                'deviations'   => $deviations,
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
