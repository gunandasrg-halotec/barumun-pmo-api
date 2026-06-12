<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Dashboard aggregation — only uses approved data.
     */
    public function dashboard(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched',
                'data' => [
                    'has_baseline' => false,
                    'message' => 'No active baseline available for this project.',
                ],
            ]);
        }

        $activeVersionId = $project->active_wbd_version_id;

        // Total baseline cost from active WBD version root nodes
        $totalBaselineCost = WbdNode::where('wbd_version_id', $activeVersionId)
            ->whereNull('parent_node_id')
            ->sum('planned_cost');

        // Total approved actual cost
        $totalApprovedCost = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->sum('amount');

        // Total official progress entries count
        $totalOfficialProgress = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->count();

        // Pending items
        $pendingProgress = ProgressEntry::where('project_id', $project->id)
            ->where('status', 'PENDING_PM_APPROVAL')
            ->count();

        $pendingCost = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'REVIEW')
            ->count();

        // Cost deviation
        $costDeviation = $totalApprovedCost - $totalBaselineCost;

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data fetched successfully',
            'data' => [
                'has_baseline' => true,
                'active_baseline_version' => $project->activeWbdVersion->version_number,
                'total_baseline_cost' => (float) $totalBaselineCost,
                'total_actual_cost_approved' => (float) $totalApprovedCost,
                'cost_deviation' => (float) $costDeviation,
                'cost_deviation_percent' => $totalBaselineCost > 0
                    ? round(($costDeviation / $totalBaselineCost) * 100, 2)
                    : 0,
                'total_official_progress_entries' => $totalOfficialProgress,
                'pending_progress_approval' => $pendingProgress,
                'pending_cost_review' => $pendingCost,
            ],
        ]);
    }

    /**
     * Gantt data — read-only from active baseline WBD nodes.
     */
    public function gantt(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'Gantt data fetched',
                'data' => [],
            ]);
        }

        $nodes = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
            ->orderBy('sort_order')
            ->get();

        // For each ITEM node, get approved progress total volume
        $progressByNode = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->selectRaw('wbd_node_id, SUM(progress_volume) as total_volume')
            ->groupBy('wbd_node_id')
            ->pluck('total_volume', 'wbd_node_id');

        $ganttData = $nodes->map(function ($node) use ($progressByNode) {
            $actualVolume = $progressByNode[$node->id] ?? 0;
            $progressPercent = ($node->volume && $node->volume > 0)
                ? min(100, round(($actualVolume / $node->volume) * 100, 2))
                : 0;

            return [
                'id' => $node->id,
                'parent_node_id' => $node->parent_node_id,
                'node_type' => $node->node_type,
                'code' => $node->code,
                'name' => $node->name,
                'start_date' => $node->start_date?->toDateString(),
                'end_date' => $node->end_date?->toDateString(),
                'duration_days' => $node->duration_days,
                'planned_cost' => $node->planned_cost,
                'volume' => $node->volume,
                'unit' => $node->unit,
                'status' => $node->status,
                'sort_order' => $node->sort_order,
                'actual_volume' => (float) $actualVolume,
                'progress_percent' => $progressPercent,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Gantt data fetched successfully',
            'data' => $ganttData,
        ]);
    }

    /**
     * S-Curve — cumulative plan vs actual by period.
     * Only uses approved progress and approved cost.
     */
    public function sCurve(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'S-Curve data fetched',
                'data' => ['plan' => [], 'actual' => []],
            ]);
        }

        // Get official progress by date (monthly aggregation)
        $actualProgress = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->selectRaw("DATE_FORMAT(progress_date, '%Y-%m') as period, SUM(progress_volume) as volume")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $actualCost = ActualCostTransaction::where('project_id', $project->id)
            ->where('status', 'APPROVED')
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as period, SUM(amount) as amount")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Build cumulative series
        $cumulativeVolume = 0;
        $cumulativeCost = 0;

        $actualSeries = [];
        foreach ($actualProgress as $row) {
            $cumulativeVolume += $row->volume;
            $actualSeries[$row->period] = [
                'period' => $row->period,
                'cumulative_volume' => $cumulativeVolume,
            ];
        }

        foreach ($actualCost as $row) {
            $cumulativeCost += $row->amount;
            if (isset($actualSeries[$row->period])) {
                $actualSeries[$row->period]['cumulative_cost'] = $cumulativeCost;
            } else {
                $actualSeries[$row->period] = [
                    'period' => $row->period,
                    'cumulative_volume' => $cumulativeVolume,
                    'cumulative_cost' => $cumulativeCost,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'S-Curve data fetched successfully',
            'data' => [
                'actual_series' => array_values($actualSeries),
            ],
        ]);
    }

    /**
     * Cost analysis per WBD group/item.
     */
    public function costAnalysis(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'Cost analysis fetched',
                'data' => [],
            ]);
        }

        $nodes = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
            ->whereNull('parent_node_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        // Actual approved cost per node via progress_entries
        $actualCostByNode = DB::table('actual_cost_transactions as act')
            ->join('progress_entries as pe', 'pe.id', '=', 'act.progress_entry_id')
            ->where('act.project_id', $project->id)
            ->where('act.status', 'APPROVED')
            ->selectRaw('pe.wbd_node_id, SUM(act.amount) as total_actual')
            ->groupBy('pe.wbd_node_id')
            ->pluck('total_actual', 'wbd_node_id');

        $totalBaseline = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
            ->whereNull('parent_node_id')
            ->sum('planned_cost');

        $analysis = $nodes->map(function ($node) use ($actualCostByNode, $totalBaseline) {
            $actualCost = $actualCostByNode[$node->id] ?? 0;
            $deviation = $actualCost - $node->planned_cost;
            $weight = $totalBaseline > 0 ? ($node->planned_cost / $totalBaseline) * 100 : 0;

            return [
                'id' => $node->id,
                'code' => $node->code,
                'name' => $node->name,
                'node_type' => $node->node_type,
                'baseline_cost' => (float) $node->planned_cost,
                'actual_cost_approved' => (float) $actualCost,
                'deviation' => (float) $deviation,
                'deviation_percent' => $node->planned_cost > 0
                    ? round(($deviation / $node->planned_cost) * 100, 2)
                    : 0,
                'weight_percent' => round($weight, 2),
                'is_over_budget' => $deviation > 0,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Cost analysis fetched successfully',
            'data' => $analysis,
        ]);
    }
}
