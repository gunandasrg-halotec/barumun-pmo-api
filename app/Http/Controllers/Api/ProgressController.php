<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProgressRequest;
use App\Http\Resources\ProgressResource;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class ProgressController extends Controller
{
    public function __construct(private ProgressService $progressService) {}

    // ─── GET /v1/projects/{project}/progress-entries ─────────────────────────

    #[GetIndexRequest(
        tags: [PROGRESS_TAG],
        path: "/v1/projects/{project}/progress-entries",
        operationId: "ProgressController@index",
        summary: "List progress entries for a project. All authenticated users.",
        security: [Auth_JWT],
        parameters: [
            "/v1/projects/{project}",
        ],
        filters: [
            new OA\Parameter(in: "query", name: "filter[status]",
                description: "Filter by status",
                schema: new Schema(type: "string",
                    enum: ["PENDING_PM_APPROVAL", "AUTO_APPROVED", "APPROVED", "REJECTED"])),
            new OA\Parameter(in: "query", name: "filter[wbd_node_id]",
                description: "Filter by WBD node UUID",
                schema: new Schema(type: "string", format: "uuid")),
            new OA\Parameter(in: "query", name: "filter[date_from]",
                description: "From date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "filter[date_to]",
                description: "To date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/progress_resource.yaml", description: "Progress entry list")]
    #[ResponseDefault()]
    public function index(ProgressRequest $request, Project $project): JsonResponse
    {
        $query = ProgressEntry::with(['wbdNode', 'enteredByUser.role', 'approvedByUser', 'rejectedByUser'])
            ->where('project_id', $project->id)
            ->orderByDesc('progress_date');

        $filter = $request->input('filter', []);

        if (!empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        if (!empty($filter['wbd_node_id'])) {
            $query->where('wbd_node_id', $filter['wbd_node_id']);
        }
        if (!empty($filter['date_from'])) {
            $query->whereDate('progress_date', '>=', $filter['date_from']);
        }
        if (!empty($filter['date_to'])) {
            $query->whereDate('progress_date', '<=', $filter['date_to']);
        }

        $entries = $query->paginate($request->get('per-page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Progress entries fetched successfully',
            'data'    => ProgressResource::collection($entries),
            'meta'    => [
                'page'  => $entries->currentPage(),
                'limit' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    // ─── POST /v1/projects/{project}/progress-entries ────────────────────────

    #[OA\Post(
        tags: [PROGRESS_TAG],
        path: "/v1/projects/{project}/progress-entries",
        operationId: "ProgressController@store",
        summary: "Create a progress entry. PM → AUTO_APPROVED. Admin Proyek → PENDING_PM_APPROVAL. Requires active baseline.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/new_progress_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Progress entry created")]
    #[ResponseDefault()]
    public function store(ProgressRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $node = WbdNode::findOrFail($data['wbd_node_id']);

        $entry = $this->progressService->createProgress($project, $node, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Progress entry created successfully',
            'data'    => new ProgressResource($entry),
        ], 201);
    }

    // ─── GET /v1/progress-entries/{progressEntry} ────────────────────────────

    #[OA\Get(
        tags: [PROGRESS_TAG],
        path: "/v1/progress-entries/{progressEntry}",
        operationId: "ProgressController@show",
        summary: "Get progress entry detail with linked cost transactions.",
        parameters: [
            new OA\Parameter(in: "path", name: "progressEntry", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Progress entry detail")]
    #[ResponseDefault()]
    public function show(ProgressRequest $request, ProgressEntry $progressEntry): JsonResponse
    {
        $progressEntry->load([
            'project', 'wbdNode', 'enteredByUser.role',
            'approvedByUser', 'rejectedByUser', 'actualCostTransactions.enteredByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Progress entry fetched successfully',
            'data'    => new ProgressResource($progressEntry),
        ]);
    }

    // ─── POST /v1/progress-entries/{progressEntry}/approve ───────────────────

    #[OA\Post(
        tags: [PROGRESS_TAG],
        path: "/v1/progress-entries/{progressEntry}/approve",
        operationId: "ProgressController@approve",
        summary: "Approve a PENDING_PM_APPROVAL progress entry. Allowed: Project Manager only.",
        parameters: [
            new OA\Parameter(in: "path", name: "progressEntry", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Progress entry approved")]
    #[ResponseDefault()]
    public function approve(ProgressRequest $request, ProgressEntry $progressEntry): JsonResponse
    {
        $entry = $this->progressService->approveProgress($progressEntry, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Progress entry approved successfully',
            'data'    => new ProgressResource($entry->load(['approvedByUser', 'wbdNode', 'enteredByUser.role'])),
        ]);
    }

    // ─── POST /v1/progress-entries/{progressEntry}/reject ────────────────────

    #[OA\Post(
        tags: [PROGRESS_TAG],
        path: "/v1/progress-entries/{progressEntry}/reject",
        operationId: "ProgressController@reject",
        summary: "Reject a PENDING_PM_APPROVAL progress entry. Allowed: Project Manager only.",
        parameters: [
            new OA\Parameter(in: "path", name: "progressEntry", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/reject_reason_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Progress entry rejected")]
    #[ResponseDefault()]
    public function reject(ProgressRequest $request, ProgressEntry $progressEntry): JsonResponse
    {
        $entry = $this->progressService->rejectProgress(
            $progressEntry,
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress entry rejected',
            'data'    => new ProgressResource($entry->load(['rejectedByUser', 'wbdNode', 'enteredByUser.role'])),
        ]);
    }
}
