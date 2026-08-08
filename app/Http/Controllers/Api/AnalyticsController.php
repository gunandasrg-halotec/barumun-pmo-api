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

        // Approved actual cost — filter to active WBD version only
        $totalApprovedCost = (float) ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->whereHas('progressEntry', fn ($q) => $q->whereIn('status', ['APPROVED', 'AUTO_APPROVED']))
            ->whereHas('progressEntry.wbdNode', fn ($q) => $q->where('wbd_version_id', $activeVersionId))
            ->sum('amount');

        // Official progress — approved entries, active WBD version only
        $officialProgressCount = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->whereHas('wbdNode', fn ($q) => $q->where('wbd_version_id', $activeVersionId))
            ->count();

        // Pending items — active WBD version only
        $pendingProgressCount = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['PENDING_PM_APPROVAL', 'PENDING_DIRECTOR_APPROVAL'])
            ->whereHas('wbdNode', fn ($q) => $q->where('wbd_version_id', $activeVersionId))
            ->count();

        $pendingCostCount = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'REVIEW')
            ->whereHas('progressEntry.wbdNode', fn ($q) => $q->where('wbd_version_id', $activeVersionId))
            ->count();

        // ── Realisasi totals — filter to active WBD version only ────────────────
        $approvedVolume = (float) ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->whereHas('wbdNode', fn ($q) => $q->where('wbd_version_id', $activeVersionId))
            ->sum('progress_volume');

        // ── Planned progress % from baseline schedule (volume & cost) ─────────
        // Distribute each ITEM node's volume/cost linearly over start_date→end_date,
        // then cumulate up to today to get "how much should have been done by now".
        $today = \Carbon\Carbon::today();
        $todayPeriod = $today->format('Y-m');

        $allNodesForDashboard = WbdNode::where('wbd_version_id', $activeVersionId)
            ->get()
            ->keyBy('id');

        $totalPlannedVolume    = 0.0;
        $totalPlannedCost      = 0.0;
        $scheduledVolToDate    = 0.0;
        $scheduledCostToDate   = 0.0;

        foreach ($allNodesForDashboard->filter(fn ($n) => $n->node_type === 'ITEM') as $node) {
            $totalPlannedVolume += (float) $node->volume;
            $totalPlannedCost   += (float) $node->planned_cost;

            // Resolve dates: use node's own, or walk up ancestors
            $startDate = $node->start_date;
            $endDate   = $node->end_date;

            if (!$startDate || !$endDate) {
                $parentId = $node->parent_node_id;
                while ($parentId && isset($allNodesForDashboard[$parentId])) {
                    $parent = $allNodesForDashboard[$parentId];
                    if ($parent->start_date && $parent->end_date) {
                        $startDate = $parent->start_date;
                        $endDate   = $parent->end_date;
                        break;
                    }
                    $parentId = $parent->parent_node_id;
                }
            }

            if (!$startDate || !$endDate) {
                continue;
            }

            $start        = \Carbon\Carbon::parse($startDate)->startOfMonth();
            $end          = \Carbon\Carbon::parse($endDate)->startOfMonth();
            $months       = $start->diffInMonths($end) + 1;
            $volPerMonth  = $months > 0 ? ((float) $node->volume / $months) : 0;
            $costPerMonth = $months > 0 ? ((float) $node->planned_cost / $months) : 0;

            $cur = $start->copy();
            for ($i = 0; $i < $months; $i++) {
                if ($cur->format('Y-m') <= $todayPeriod) {
                    $scheduledVolToDate  += $volPerMonth;
                    $scheduledCostToDate += $costPerMonth;
                }
                $cur->addMonth();
            }
        }

        // Planned % = scheduled-to-date / total planned
        $plannedProgressPercent = $totalPlannedVolume > 0
            ? min(100, round(($scheduledVolToDate / $totalPlannedVolume) * 100, 2))
            : 0;

        $plannedCostPercent = $totalPlannedCost > 0
            ? min(100, round(($scheduledCostToDate / $totalPlannedCost) * 100, 2))
            : 0;

        // ── Actual progress % (completion estimate) ───────────────────────────
        // Sisa per node = field "Sisa Estimasi" dari entri APPROVED/AUTO_APPROVED
        // TERAKHIR (menghormati override manual user), bukan hitung ulang
        // Rencana - Realisasi — fallback ke Rencana penuh bila belum ada entri
        // disetujui. Konsisten dengan mekanisme di WbdNodeResource/ProgressListPage.
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
            ->join('wbd_nodes as wn', 'pe.wbd_node_id', '=', 'wn.id')
            ->where('pe.project_id', $project->id)
            ->whereIn('pe.status', ['APPROVED', 'AUTO_APPROVED'])
            ->where('wn.wbd_version_id', $activeVersionId)
            ->select('pe.wbd_node_id', 'pe.remaining_volume', 'pe.remaining_cost')
            ->get()
            ->keyBy('wbd_node_id');

        $totalRemainingVolume = 0.0;
        $totalRemainingCost   = 0.0;
        foreach ($allNodesForDashboard->filter(fn ($n) => $n->node_type === 'ITEM') as $node) {
            $latest = $latestRemainingByNode[$node->id] ?? null;
            $totalRemainingVolume += $latest?->remaining_volume !== null
                ? (float) $latest->remaining_volume
                : (float) $node->volume;
            $totalRemainingCost += $latest?->remaining_cost !== null
                ? (float) $latest->remaining_cost
                : (float) $node->planned_cost;
        }

        // actual volume: realisasi / (realisasi + sisa kumulatif per node)
        $volDenominator        = $approvedVolume + $totalRemainingVolume;
        $actualProgressPercent = $volDenominator > 0
            ? min(100, round(($approvedVolume / $volDenominator) * 100, 2))
            : 0;

        // actual cost: biaya_realisasi / (biaya_realisasi + sisa_biaya kumulatif per node)
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
                'cost_vs_baseline_percent'      => $totalBaselineCost > 0
                    ? min(100, round(($totalApprovedCost / $totalBaselineCost) * 100, 2))
                    : 0,
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

        // ── Actual series — only from active WBD version nodes ───────────────
        $activeVersionId = $project->active_wbd_version_id;

        $progressByMonth = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->when($activeVersionId, fn ($q) => $q->whereHas('wbdNode', fn ($nq) => $nq->where('wbd_version_id', $activeVersionId)))
            ->selectRaw("DATE_FORMAT(progress_date, '%Y-%m') as period, SUM(progress_volume) as volume")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $costByMonth = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->whereHas('progressEntry', fn ($q) => $q->whereIn('status', ['APPROVED', 'AUTO_APPROVED']))
            ->when($activeVersionId, fn ($q) => $q->whereHas('progressEntry.wbdNode', fn ($nq) => $nq->where('wbd_version_id', $activeVersionId)))
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
            // Load all nodes (ITEM + GROUP) so we can fall back to parent dates
            $allNodes = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
                ->get()
                ->keyBy('id');

            $itemNodes = $allNodes->filter(fn ($n) => $n->node_type === 'ITEM');

            foreach ($itemNodes as $node) {
                $totalPlannedVolume += (float) $node->volume;

                // Resolve dates: use node's own dates, or walk up ancestors for fallback
                $startDate = $node->start_date;
                $endDate   = $node->end_date;

                if (!$startDate || !$endDate) {
                    $parentId = $node->parent_node_id;
                    while ($parentId && isset($allNodes[$parentId])) {
                        $parent = $allNodes[$parentId];
                        if ($parent->start_date && $parent->end_date) {
                            $startDate = $parent->start_date;
                            $endDate   = $parent->end_date;
                            break;
                        }
                        $parentId = $parent->parent_node_id;
                    }
                }

                if (!$startDate || !$endDate) {
                    // No date available anywhere in ancestor chain — skip distribution only
                    continue;
                }

                $start        = \Carbon\Carbon::parse($startDate)->startOfMonth();
                $end          = \Carbon\Carbon::parse($endDate)->startOfMonth();
                $months       = $start->diffInMonths($end) + 1;
                $volPerMonth  = $months > 0 ? ((float) $node->volume / $months) : 0;
                $costPerMonth = $months > 0 ? ((float) $node->planned_cost / $months) : 0;

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
                ->whereIn('pe.status', ['APPROVED', 'AUTO_APPROVED'])
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
            ->whereIn('pe.status', ['APPROVED', 'AUTO_APPROVED'])
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
