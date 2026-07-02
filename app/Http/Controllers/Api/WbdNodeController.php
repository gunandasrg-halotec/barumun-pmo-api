<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\WbdNodeRequest;
use App\Http\Resources\WbdNodeResource;
use App\Models\WbdNode;
use App\Models\WbdVersion;
use App\Services\WbdService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class WbdNodeController extends Controller
{
    public function __construct(private WbdService $wbdService) {}

    // ─── GET /v1/wbd-versions/{wbdVersion}/nodes ─────────────────────────────

    #[OA\Get(
        tags: [WBD_NODE_TAG],
        path: "/v1/wbd-versions/{wbdVersion}/nodes",
        operationId: "WbdNodeController@index",
        summary: "Get all WBD nodes for a version (nested tree). All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD node tree")]
    #[ResponseDefault()]
    public function index(WbdNodeRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        $nodes = $wbdVersion->nodes()->with(['children', 'predecessorDependencies.predecessor'])->get();
        $tree  = $this->buildTree($nodes);

        return response()->json([
            'success' => true,
            'message' => 'WBD nodes fetched successfully',
            'data'    => $tree,
        ]);
    }

    // ─── POST /v1/wbd-versions/{wbdVersion}/nodes ────────────────────────────

    #[OA\Post(
        tags: [WBD_NODE_TAG],
        path: "/v1/wbd-versions/{wbdVersion}/nodes",
        operationId: "WbdNodeController@store",
        summary: "Create a WBD node in a DRAFT version. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/new_wbd_node_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "WBD node created")]
    #[ResponseDefault()]
    public function store(WbdNodeRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        if (!$wbdVersion->canBeEdited()) {
            abort(422, 'Only DRAFT WBD versions can be edited.');
        }

        $data = $request->validated();

        // Validate parent belongs to same version
        if (!empty($data['parent_node_id'])) {
            $parent = WbdNode::findOrFail($data['parent_node_id']);
            if ($parent->wbd_version_id !== $wbdVersion->id) {
                abort(422, 'Parent node must belong to the same WBD version.');
            }
        }

        // Unique code within version
        if (WbdNode::where('wbd_version_id', $wbdVersion->id)->where('code', $data['code'])->exists()) {
            abort(422, 'Node code must be unique within this WBD version.');
        }

        // Calculate derived fields
        $endDate     = null;
        $plannedCost = 0;

        if (!empty($data['start_date']) && !empty($data['duration_days'])) {
            $endDate = \Carbon\Carbon::parse($data['start_date'])->addDays($data['duration_days'] - 1)->toDateString();
        }

        if ($data['node_type'] === 'ITEM') {
            $plannedCost = ($data['volume'] ?? 0) * ($data['rate'] ?? 0);
        }

        $node = WbdNode::create([
            'wbd_version_id' => $wbdVersion->id,
            'parent_node_id' => $data['parent_node_id'] ?? null,
            'node_type'      => $data['node_type'],
            'code'           => $data['code'],
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'unit'           => $data['unit'] ?? null,
            'volume'         => $data['volume'] ?? null,
            'rate'           => $data['rate'] ?? null,
            'planned_cost'   => $plannedCost,
            'start_date'     => $data['start_date'] ?? null,
            'duration_days'  => $data['duration_days'] ?? null,
            'end_date'       => $endDate,
            'status'         => $data['status'] ?? 'ACTIVE',
            'sort_order'     => $data['sort_order'] ?? 0,
        ]);

        $this->wbdService->recalculate($wbdVersion);

        return response()->json([
            'success' => true,
            'message' => 'WBD node created successfully',
            'data'    => new WbdNodeResource($node->fresh()),
        ], 201);
    }

    // ─── PATCH /v1/wbd-nodes/{wbdNode} ───────────────────────────────────────

    #[OA\Patch(
        tags: [WBD_NODE_TAG],
        path: "/v1/wbd-nodes/{wbdNode}",
        operationId: "WbdNodeController@update",
        summary: "Update a WBD node in a DRAFT version. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdNode", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/update_wbd_node_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD node updated")]
    #[ResponseDefault()]
    public function update(WbdNodeRequest $request, WbdNode $wbdNode): JsonResponse
    {
        $version = $wbdNode->wbdVersion;

        if (!$version->canBeEdited()) {
            abort(422, 'Only DRAFT WBD versions can be edited.');
        }

        $data = $request->validated();

        // Recalculate end_date if schedule changed
        if (isset($data['start_date']) || isset($data['duration_days'])) {
            $startDate = $data['start_date']   ?? $wbdNode->start_date?->toDateString();
            $duration  = $data['duration_days'] ?? $wbdNode->duration_days;
            if ($startDate && $duration) {
                $data['end_date'] = \Carbon\Carbon::parse($startDate)->addDays($duration - 1)->toDateString();
            }
        }

        // Recalculate planned_cost if volume or rate changed
        if (isset($data['volume']) || isset($data['rate'])) {
            $volume = $data['volume'] ?? $wbdNode->volume;
            $rate   = $data['rate']   ?? $wbdNode->rate;
            $data['planned_cost'] = ($volume ?? 0) * ($rate ?? 0);
        }

        $scheduleChanged = isset($data['start_date']) || isset($data['duration_days']);

        $wbdNode->update($data);
        $this->wbdService->recalculate($version);

        // If dates changed, cascade to all dependency successors
        if ($scheduleChanged) {
            app(WbdNodeDependencyController::class)->cascadeFrom($wbdNode);
        }

        return response()->json([
            'success' => true,
            'message' => 'WBD node updated successfully',
            'data'    => new WbdNodeResource($wbdNode->fresh()),
        ]);
    }

    // ─── DELETE /v1/wbd-nodes/{wbdNode} ──────────────────────────────────────

    #[OA\Delete(
        tags: [WBD_NODE_TAG],
        path: "/v1/wbd-nodes/{wbdNode}",
        operationId: "WbdNodeController@destroy",
        summary: "Delete a WBD node from a DRAFT version. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdNode", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD node deleted")]
    #[ResponseDefault()]
    public function destroy(WbdNodeRequest $request, WbdNode $wbdNode): JsonResponse
    {
        $version = $wbdNode->wbdVersion;

        if (!$version->canBeEdited()) {
            abort(422, 'Only DRAFT WBD versions can be edited.');
        }

        if ($wbdNode->progressEntries()->exists()) {
            abort(422, 'Node ini memiliki data realisasi progress dan tidak dapat dihapus.');
        }

        $this->deleteNodeRecursive($wbdNode);
        $this->wbdService->recalculate($version);

        return response()->json([
            'success' => true,
            'message' => 'WBD node deleted successfully',
        ]);
    }

    private function deleteNodeRecursive(WbdNode $node): void
    {
        foreach ($node->children as $child) {
            $this->deleteNodeRecursive($child);
        }
        $node->delete();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildTree($nodes, ?string $parentId = null): array
    {
        return $nodes
            ->filter(fn ($n) => $n->parent_node_id === $parentId)
            ->map(function ($node) use ($nodes) {
                $resource           = new WbdNodeResource($node);
                $arr                = $resource->toArray(request());
                $arr['children']    = $this->buildTree($nodes, $node->id);
                $arr['predecessors'] = $node->predecessorDependencies->map(fn ($d) => [
                    'id'              => $d->id,
                    'predecessor_id'  => $d->predecessor_node_id,
                    'code'            => $d->predecessor->code ?? '?',
                    'name'            => $d->predecessor->name ?? '?',
                    'dependency_type' => $d->dependency_type,
                ])->values()->toArray();
                return $arr;
            })
            ->values()
            ->toArray();
    }
}
