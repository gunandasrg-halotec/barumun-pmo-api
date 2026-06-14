<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

/**
 * Phase 4 — Gantt Read-Only Module.
 *
 * SRS rule: Gantt is STRICTLY read-only.
 * Data is derived from the active approved baseline.
 * Progress % is calculated from APPROVED + AUTO_APPROVED progress only.
 * No create / update / delete endpoints here.
 */
class GanttController extends Controller
{
    // ─── GET /v1/projects/{project}/gantt ────────────────────────────────────

    #[OA\Get(
        tags: [ANALYTICS_TAG],
        path: "/v1/projects/{project}/gantt",
        operationId: "GanttController@index",
        summary: "Get Gantt chart data from the active approved baseline (read-only). All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
            new OA\Parameter(
                in: "query", name: "node_type",
                description: "Filter by node_type (GROUP or ITEM)",
                schema: new Schema(type: "string", enum: ["GROUP", "ITEM"])
            ),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Gantt data — read-only, approved baseline only")]
    #[ResponseDefault()]
    public function index(Request $request, Project $project): JsonResponse
    {
        $project->load('activeWbdVersion');

        if (!$project->hasActiveBaseline()) {
            return response()->json([
                'success' => true,
                'message' => 'No active baseline. Gantt chart is unavailable.',
                'data'    => [],
                'meta'    => ['has_baseline' => false],
            ]);
        }

        $query = WbdNode::where('wbd_version_id', $project->active_wbd_version_id)
            ->orderBy('sort_order');

        if ($request->filled('node_type')) {
            $query->where('node_type', $request->node_type);
        }

        $nodes = $query->get();

        // Approved progress volume per node (official data only)
        $progressByNode = ProgressEntry::where('project_id', $project->id)
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->selectRaw('wbd_node_id, SUM(progress_volume) as total_volume')
            ->groupBy('wbd_node_id')
            ->pluck('total_volume', 'wbd_node_id');

        $ganttData = $nodes->map(function ($node) use ($progressByNode) {
            $actualVolume    = (float) ($progressByNode[$node->id] ?? 0);
            $progressPercent = ($node->volume && $node->volume > 0)
                ? min(100, round(($actualVolume / $node->volume) * 100, 2))
                : 0;

            return [
                'id'               => $node->id,
                'parent_node_id'   => $node->parent_node_id,
                'node_type'        => $node->node_type,
                'code'             => $node->code,
                'name'             => $node->name,
                'unit'             => $node->unit,
                'volume'           => $node->volume !== null ? (float) $node->volume : null,
                'planned_cost'     => $node->planned_cost !== null ? (float) $node->planned_cost : null,
                'start_date'       => $node->start_date?->toDateString(),
                'end_date'         => $node->end_date?->toDateString(),
                'duration_days'    => $node->duration_days,
                'status'           => $node->status,
                'sort_order'       => $node->sort_order,
                'actual_volume'    => $actualVolume,
                'progress_percent' => $progressPercent,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Gantt data fetched successfully',
            'data'    => $ganttData,
            'meta'    => [
                'has_baseline'     => true,
                'baseline_version' => $project->activeWbdVersion->version_number,
                'is_read_only'     => true,
            ],
        ]);
    }
}
