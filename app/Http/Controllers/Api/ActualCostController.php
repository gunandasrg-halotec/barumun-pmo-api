<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualCostRequest;
use App\Http\Resources\ActualCostResource;
use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Services\CostService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class ActualCostController extends Controller
{
    public function __construct(private CostService $costService) {}

    // ─── GET /v1/projects/{project}/actual-cost-transactions ─────────────────

    #[GetIndexRequest(
        tags: [ACTUAL_COST_TAG],
        path: "/v1/projects/{project}/actual-cost-transactions",
        operationId: "ActualCostController@index",
        summary: "List actual cost transactions for a project. All authenticated users.",
        security: [Auth_JWT],
        filters: [
            new OA\Parameter(in: "query", name: "filter[status]",
                description: "Filter by status",
                schema: new Schema(type: "string", enum: ["REVIEW", "APPROVED", "REJECTED"])),
            new OA\Parameter(in: "query", name: "filter[date_from]",
                description: "From date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "filter[date_to]",
                description: "To date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "filter[progress_entry_id]",
                description: "Filter by linked progress entry UUID",
                schema: new Schema(type: "string", format: "uuid")),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/actual_cost_resource.yaml", description: "Actual cost transaction list")]
    #[ResponseDefault()]
    public function index(ActualCostRequest $request, Project $project): JsonResponse
    {
        $query = ActualCostTransaction::with([
            'progressEntry.wbdNode',
            'enteredByUser.role',
            'reviewedByUser',
            'rejectedByUser',
        ])
            ->where('project_id', $project->id)
            ->orderByDesc('transaction_date');

        $filter = $request->input('filter', []);

        if (!empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        if (!empty($filter['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filter['date_from']);
        }
        if (!empty($filter['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filter['date_to']);
        }
        if (!empty($filter['progress_entry_id'])) {
            $query->where('progress_entry_id', $filter['progress_entry_id']);
        }

        $costs = $query->paginate($request->get('per-page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Cost transactions fetched successfully',
            'data'    => ActualCostResource::collection($costs),
            'meta'    => [
                'page'  => $costs->currentPage(),
                'limit' => $costs->perPage(),
                'total' => $costs->total(),
            ],
        ]);
    }

    // ─── POST /v1/projects/{project}/actual-cost-transactions ────────────────

    #[OA\Post(
        tags: [ACTUAL_COST_TAG],
        path: "/v1/projects/{project}/actual-cost-transactions",
        operationId: "ActualCostController@store",
        summary: "Create an actual cost transaction linked to a progress entry. Allowed: Finance, Admin Proyek (NOT PM). All entries go to REVIEW status.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/new_actual_cost_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Cost transaction created with REVIEW status")]
    #[ResponseDefault()]
    public function store(ActualCostRequest $request, Project $project): JsonResponse
    {
        $data          = $request->validated();
        $progressEntry = ProgressEntry::findOrFail($data['progress_entry_id']);

        $cost = $this->costService->createCostTransaction(
            $project,
            $progressEntry,
            $data,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction created successfully',
            'data'    => new ActualCostResource($cost),
        ], 201);
    }

    // ─── GET /v1/actual-cost-transactions/{actualCostTransaction} ────────────

    #[OA\Get(
        tags: [ACTUAL_COST_TAG],
        path: "/v1/actual-cost-transactions/{actualCostTransaction}",
        operationId: "ActualCostController@show",
        summary: "Get actual cost transaction detail. All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "actualCostTransaction", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Actual cost transaction detail")]
    #[ResponseDefault()]
    public function show(ActualCostRequest $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $actualCostTransaction->load([
            'project',
            'progressEntry.wbdNode',
            'enteredByUser.role',
            'reviewedByUser',
            'rejectedByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction fetched successfully',
            'data'    => new ActualCostResource($actualCostTransaction),
        ]);
    }

    // ─── POST /v1/actual-cost-transactions/{actualCostTransaction}/approve ────

    #[OA\Post(
        tags: [ACTUAL_COST_TAG],
        path: "/v1/actual-cost-transactions/{actualCostTransaction}/approve",
        operationId: "ActualCostController@approve",
        summary: "Approve a REVIEW cost transaction. Allowed: Finance only.",
        parameters: [
            new OA\Parameter(in: "path", name: "actualCostTransaction", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Cost transaction approved")]
    #[ResponseDefault()]
    public function approve(ActualCostRequest $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $cost = $this->costService->approveCost($actualCostTransaction, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction approved successfully',
            'data'    => new ActualCostResource(
                $cost->load(['reviewedByUser', 'progressEntry.wbdNode', 'enteredByUser.role'])
            ),
        ]);
    }

    // ─── POST /v1/actual-cost-transactions/{actualCostTransaction}/reject ─────

    #[OA\Post(
        tags: [ACTUAL_COST_TAG],
        path: "/v1/actual-cost-transactions/{actualCostTransaction}/reject",
        operationId: "ActualCostController@reject",
        summary: "Reject a REVIEW cost transaction. Allowed: Finance only.",
        parameters: [
            new OA\Parameter(in: "path", name: "actualCostTransaction", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/reject_reason_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Cost transaction rejected")]
    #[ResponseDefault()]
    public function reject(ActualCostRequest $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $cost = $this->costService->rejectCost(
            $actualCostTransaction,
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction rejected',
            'data'    => new ActualCostResource(
                $cost->load(['rejectedByUser', 'progressEntry.wbdNode', 'enteredByUser.role'])
            ),
        ]);
    }
}
