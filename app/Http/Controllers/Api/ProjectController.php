<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class ProjectController extends Controller
{
    // ─── GET /v1/projects ────────────────────────────────────────────────────

    #[GetIndexRequest(
        tags: [PROJECT_TAG],
        path: "/v1/projects",
        operationId: "ProjectController@index",
        summary: "List all projects. All authenticated users can access this endpoint.",
        security: [Auth_JWT],
        filters: [
            new OA\Parameter(
                in: "query",
                name: "filter[status]",
                description: "Filter by project status (PLANNING, ACTIVE, ON_HOLD, COMPLETED, CANCELLED)",
                schema: new Schema(
                    type: "string",
                    enum: ["PLANNING", "ACTIVE", "ON_HOLD", "COMPLETED", "CANCELLED"]
                )
            ),
            new OA\Parameter(
                in: "query",
                name: "filter[client_name]",
                description: "Filter by client name (partial match)",
                schema: new Schema(type: "string")
            ),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/project_resource.yaml", description: "Project list")]
    #[ResponseDefault()]
    public function index(ProjectRequest $request): JsonResponse
    {
        $projects = ProjectResource::collection(
            Project::projects($request->only(['search', 'filter', 'sort-by', 'sort-dir']))
                ->paginate($request->get('per-page', 20))
        );

        return response()->json([
            'success' => true,
            'message' => 'Projects fetched successfully',
            'data'    => $projects,
            'meta'    => [
                'page'  => $projects->resource->currentPage(),
                'limit' => $projects->resource->perPage(),
                'total' => $projects->resource->total(),
            ],
        ]);
    }

    // ─── GET /v1/projects/{project} ──────────────────────────────────────────

    #[OA\Get(
        tags: [PROJECT_TAG],
        path: "/v1/projects/{project}",
        operationId: "ProjectController@show",
        summary: "Get project detail. All authenticated users can access this endpoint.",
        parameters: [
            new OA\Parameter(
                in: "path",
                name: "project",
                required: true,
                schema: new Schema(type: "string", format: "uuid")
            ),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Project detail", ref: "schemas/project_resource.yaml")]
    #[ResponseDefault()]
    public function show(ProjectRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Project detail fetched successfully',
            'data'    => new ProjectResource($project),
        ]);
    }

    // ─── POST /v1/projects ───────────────────────────────────────────────────

    #[OA\Post(
        tags: [PROJECT_TAG],
        path: "/v1/projects",
        operationId: "ProjectController@store",
        summary: "Create a new project. Allowed roles: Manajer Kebun, Admin Proyek, Administrator Sistem.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: "schemas/new_project_request.yaml"
            )
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Project created", ref: "schemas/project_resource.yaml")]
    #[ResponseDefault()]
    public function store(ProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $project = Project::create([
            ...$validated,
            'status'     => $validated['status'] ?? 'ACTIVE',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data'    => new ProjectResource($project),
        ], 201);
    }

    // ─── PATCH /v1/projects/{project} ────────────────────────────────────────

    #[OA\Patch(
        tags: [PROJECT_TAG],
        path: "/v1/projects/{project}",
        operationId: "ProjectController@update",
        summary: "Update project data. Allowed roles: Manajer Kebun, Admin Proyek, Administrator Sistem.",
        parameters: [
            new OA\Parameter(
                in: "path",
                name: "project",
                required: true,
                schema: new Schema(type: "string", format: "uuid")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: "schemas/update_project_request.yaml"
            )
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Project updated", ref: "schemas/project_resource.yaml")]
    #[ResponseDefault()]
    public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data'    => new ProjectResource($project->fresh()),
        ]);
    }
}
