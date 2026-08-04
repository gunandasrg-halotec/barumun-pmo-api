<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use App\Models\WbdNodeDependency;
use App\Models\WbdVersion;
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

        // Determine which WBD version to display
        $requestedVersionId = $request->query('wbd_version_id');
        $isActiveVersion    = true;
        $versionLabel       = null;

        if ($requestedVersionId) {
            $wbdVersion = WbdVersion::where('id', $requestedVersionId)
                ->where('project_id', $project->id)
                ->first();

            if (!$wbdVersion) {
                return response()->json(['message' => 'WBD version not found.'], 404);
            }

            $versionId       = $wbdVersion->id;
            $isActiveVersion = $wbdVersion->id === $project->active_wbd_version_id;
            $versionLabel    = 'v' . $wbdVersion->version_number . ' (' . $wbdVersion->status . ')';
        } else {
            if (!$project->hasActiveBaseline()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active baseline. Gantt chart is unavailable.',
                    'data'    => [],
                    'meta'    => ['has_baseline' => false],
                ]);
            }
            $versionId    = $project->active_wbd_version_id;
            $versionLabel = 'v' . $project->activeWbdVersion->version_number . ' (aktif)';
        }

        $query = WbdNode::where('wbd_version_id', $versionId)
            ->orderBy('sort_order');

        if ($request->filled('node_type')) {
            $query->where('node_type', $request->node_type);
        }

        $nodes = $query->get();

        // Load all dependencies for nodes in this version in one query
        $nodeIds = $nodes->pluck('id');
        $dependencies = WbdNodeDependency::whereIn('successor_node_id', $nodeIds)
            ->orWhereIn('predecessor_node_id', $nodeIds)
            ->get()
            ->map(fn ($d) => [
                'id'                  => $d->id,
                'predecessor_node_id' => $d->predecessor_node_id,
                'successor_node_id'   => $d->successor_node_id,
                'dependency_type'     => $d->dependency_type,
            ]);

        // Progress only available for the active baseline version
        $progressByNode = [];
        $actualDatesByNode = [];
        if ($isActiveVersion) {
            $progressByNode = ProgressEntry::where('project_id', $project->id)
                ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
                ->selectRaw('wbd_node_id, SUM(progress_volume) as total_volume')
                ->groupBy('wbd_node_id')
                ->pluck('total_volume', 'wbd_node_id')
                ->toArray();

            $actualDatesByNode = ProgressEntry::where('project_id', $project->id)
                ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
                ->selectRaw('wbd_node_id, MIN(progress_date) as first_date, MAX(progress_date) as last_date')
                ->groupBy('wbd_node_id')
                ->get()
                ->keyBy('wbd_node_id')
                ->toArray();
        }

        $totalBaselineCost = (float) $nodes->whereNull('parent_node_id')->sum('planned_cost');
        $today = now()->startOfDay();

        $ganttData = $nodes->map(function ($node) use ($progressByNode, $actualDatesByNode, $isActiveVersion, $totalBaselineCost, $today) {
            $actualVolume    = (float) ($progressByNode[$node->id] ?? 0);
            $progressPercent = ($isActiveVersion && $node->volume && $node->volume > 0)
                ? min(100, round(($actualVolume / $node->volume) * 100, 2))
                : 0;

            $weightPercent = $totalBaselineCost > 0
                ? round(((float) $node->planned_cost / $totalBaselineCost) * 100, 2)
                : 0;

            $actualStartDate = null;
            $actualEndDate   = null;
            $expectedProgressPercent = null;
            $scheduleStatus  = 'NO_DATA';

            if ($node->node_type === 'ITEM' && $isActiveVersion) {
                $dateRow = $actualDatesByNode[$node->id] ?? null;
                $actualStartDate = $dateRow['first_date'] ?? null;

                $isCompleted = $node->volume && $node->volume > 0 && $actualVolume >= (float) $node->volume;
                if ($isCompleted) {
                    $actualEndDate = $dateRow['last_date'] ?? null;
                }

                if ($node->start_date && $node->end_date) {
                    $startMs = $node->start_date->timestamp;
                    $endMs   = $node->end_date->timestamp;
                    $todayMs = $today->timestamp;

                    $expectedProgressPercent = $todayMs <= $startMs ? 0.0
                        : ($todayMs >= $endMs ? 100.0
                            : round((($todayMs - $startMs) / max(1, $endMs - $startMs)) * 100, 2));

                    if ($isCompleted) {
                        $scheduleStatus = ($actualEndDate && $actualEndDate <= $node->end_date->toDateString())
                            ? 'COMPLETED_ON_TIME'
                            : 'COMPLETED_LATE';
                    } elseif ($today->timestamp > $endMs) {
                        $scheduleStatus = 'DELAYED';
                    } elseif (!$actualStartDate) {
                        $scheduleStatus = 'NOT_STARTED';
                    } else {
                        $diff = $progressPercent - $expectedProgressPercent;
                        $scheduleStatus = $diff >= 5 ? 'AHEAD' : ($diff <= -10 ? 'DELAYED' : 'ON_TRACK');
                    }
                }
            }

            return [
                'id'                        => $node->id,
                'parent_node_id'            => $node->parent_node_id,
                'node_type'                 => $node->node_type,
                'code'                      => $node->code,
                'name'                      => $node->name,
                'unit'                      => $node->unit,
                'volume'                    => $node->volume !== null ? (float) $node->volume : null,
                'planned_cost'              => $node->planned_cost !== null ? (float) $node->planned_cost : null,
                'start_date'                => $node->start_date?->toDateString(),
                'end_date'                  => $node->end_date?->toDateString(),
                'duration_days'             => $node->duration_days,
                'status'                    => $node->status,
                'sort_order'                => $node->sort_order,
                'actual_volume'             => $actualVolume,
                'progress_percent'          => $progressPercent,
                'weight_percent'            => $weightPercent,
                'actual_start_date'         => $actualStartDate,
                'actual_end_date'           => $actualEndDate,
                'expected_progress_percent' => $expectedProgressPercent,
                'schedule_status'           => $scheduleStatus,
            ];
        });

        // GROUP nodes have no start/end date in DB — derive from their ITEM descendants.
        $indexed = $ganttData->keyBy('id')->toArray();

        $ganttData = $ganttData->map(function ($row) use (&$indexed) {
            if ($row['node_type'] !== 'GROUP' || ($row['start_date'] && $row['end_date'])) {
                return $row;
            }

            // Collect all descendant start/end dates
            $starts = [];
            $ends   = [];
            $stack  = [$row['id']];
            while (!empty($stack)) {
                $pid = array_pop($stack);
                foreach ($indexed as $r) {
                    if ($r['parent_node_id'] !== $pid) continue;
                    if ($r['start_date']) $starts[] = $r['start_date'];
                    if ($r['end_date'])   $ends[]   = $r['end_date'];
                    $stack[] = $r['id'];
                }
            }

            if (!empty($starts)) {
                $row['start_date'] = min($starts);
                $row['end_date']   = max($ends);
            }

            return $row;
        });

        return response()->json([
            'success' => true,
            'message' => 'Gantt data fetched successfully',
            'data'    => $ganttData,
            'meta'    => [
                'has_baseline'      => true,
                'baseline_version'  => $project->activeWbdVersion?->version_number,
                'is_read_only'      => true,
                'is_active_version' => $isActiveVersion,
                'version_label'     => $versionLabel,
                'version_id'        => $versionId,
            ],
            'dependencies' => $dependencies->values(),
        ]);
    }
}
