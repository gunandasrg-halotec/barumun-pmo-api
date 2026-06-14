<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\WbdVersionRequest;
use App\Http\Resources\WbdVersionResource;
use App\Models\Project;
use App\Models\WbdVersion;
use App\Services\WbdService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class WbdVersionController extends Controller
{
    public function __construct(private WbdService $wbdService) {}

    // ─── GET /v1/projects/{project}/wbd-versions ─────────────────────────────

    #[OA\Get(
        tags: [WBD_TAG],
        path: "/v1/projects/{project}/wbd-versions",
        operationId: "WbdVersionController@index",
        summary: "List all WBD versions for a project. All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD version list")]
    #[ResponseDefault()]
    public function index(WbdVersionRequest $request, Project $project): JsonResponse
    {
        $versions = $project->wbdVersions()
            ->with(['submittedByUser', 'approvedByUser', 'rejectedByUser'])
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'WBD versions fetched successfully',
            'data'    => WbdVersionResource::collection($versions),
        ]);
    }

    // ─── POST /v1/projects/{project}/wbd-versions ────────────────────────────

    #[OA\Post(
        tags: [WBD_TAG],
        path: "/v1/projects/{project}/wbd-versions",
        operationId: "WbdVersionController@store",
        summary: "Create a new DRAFT WBD version. Optionally copy nodes from an existing version. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: "schemas/new_wbd_version_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "WBD draft version created")]
    #[ResponseDefault()]
    public function store(WbdVersionRequest $request, Project $project): JsonResponse
    {
        $version = $this->wbdService->createDraftVersion(
            $project,
            $request->based_on_version_id,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'WBD draft version created successfully',
            'data'    => new WbdVersionResource($version->load(['submittedByUser', 'approvedByUser'])),
        ], 201);
    }

    // ─── GET /v1/wbd-versions/pending ────────────────────────────────────────

    #[OA\Get(
        tags: [WBD_TAG],
        path: "/v1/wbd-versions/pending",
        operationId: "WbdVersionController@pending",
        summary: "List all WBD versions pending Direksi approval (global, all projects). Allowed: Direksi.",
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Pending WBD versions")]
    #[ResponseDefault()]
    public function pending(WbdVersionRequest $request): JsonResponse
    {
        $versions = WbdVersion::with(['project', 'submittedByUser'])
            ->where('status', 'PENDING_DIRECTOR_APPROVAL')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Pending WBD versions fetched successfully',
            'data'    => WbdVersionResource::collection($versions),
        ]);
    }

    // ─── GET /v1/wbd-versions/{wbdVersion} ───────────────────────────────────

    #[OA\Get(
        tags: [WBD_TAG],
        path: "/v1/wbd-versions/{wbdVersion}",
        operationId: "WbdVersionController@show",
        summary: "Get WBD version detail. All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD version detail")]
    #[ResponseDefault()]
    public function show(WbdVersionRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        $wbdVersion->load(['project', 'submittedByUser', 'approvedByUser', 'rejectedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'WBD version detail fetched successfully',
            'data'    => new WbdVersionResource($wbdVersion),
        ]);
    }

    // ─── POST /v1/wbd-versions/{wbdVersion}/submit ───────────────────────────

    #[OA\Post(
        tags: [WBD_TAG],
        path: "/v1/wbd-versions/{wbdVersion}/submit",
        operationId: "WbdVersionController@submit",
        summary: "Submit a DRAFT WBD version for Direksi approval. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD version submitted for approval")]
    #[ResponseDefault()]
    public function submit(WbdVersionRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        $version = $this->wbdService->submitForApproval($wbdVersion, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'WBD version submitted for Direksi approval',
            'data'    => new WbdVersionResource($version->load(['submittedByUser'])),
        ]);
    }

    // ─── POST /v1/wbd-versions/{wbdVersion}/approve ──────────────────────────

    #[OA\Post(
        tags: [WBD_TAG],
        path: "/v1/wbd-versions/{wbdVersion}/approve",
        operationId: "WbdVersionController@approve",
        summary: "Approve a WBD version and set it as active baseline. Allowed: Direksi only.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD version approved and set as active baseline")]
    #[ResponseDefault()]
    public function approve(WbdVersionRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        $version = $this->wbdService->approveVersion($wbdVersion, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'WBD version approved and set as active baseline',
            'data'    => new WbdVersionResource($version->load(['approvedByUser'])),
        ]);
    }

    // ─── POST /v1/wbd-versions/{wbdVersion}/reject ───────────────────────────

    #[OA\Post(
        tags: [WBD_TAG],
        path: "/v1/wbd-versions/{wbdVersion}/reject",
        operationId: "WbdVersionController@reject",
        summary: "Reject a WBD version with a reason. Allowed: Direksi only.",
        parameters: [
            new OA\Parameter(in: "path", name: "wbdVersion", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/reject_reason_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "WBD version rejected")]
    #[ResponseDefault()]
    public function reject(WbdVersionRequest $request, WbdVersion $wbdVersion): JsonResponse
    {
        $version = $this->wbdService->rejectVersion(
            $wbdVersion,
            $request->user()->id,
            $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'WBD version rejected',
            'data'    => new WbdVersionResource($version->load(['rejectedByUser'])),
        ]);
    }
}
