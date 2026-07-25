<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectFileRequest;
use App\Http\Resources\ProjectFileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class ProjectFileController extends Controller
{
    // ─── GET /v1/projects/{project}/files ────────────────────────────────────

    #[GetIndexRequest(
        tags: [FILE_TAG],
        path: "/v1/projects/{project}/files",
        operationId: "ProjectFileController@index",
        summary: "List active files for a project. Supports type and category filtering.",
        security: [Auth_JWT],
        filters: [
            new OA\Parameter(
                in: "query",
                name: "filter[file_type]",
                description: "Filter by file type",
                schema: new Schema(type: "string", enum: ["DOCUMENT", "IMAGE"])
            ),
            new OA\Parameter(
                in: "query",
                name: "filter[file_category_id]",
                description: "Filter by category UUID",
                schema: new Schema(type: "string", format: "uuid")
            ),
            new OA\Parameter(
                in: "query",
                name: "filter[related_entity_type]",
                description: "Filter by related entity type",
                schema: new Schema(type: "string", enum: ["WBD_NODE", "PROGRESS_ENTRY"])
            ),
            new OA\Parameter(
                in: "query",
                name: "filter[related_entity_id]",
                description: "Filter by related entity UUID",
                schema: new Schema(type: "string", format: "uuid")
            ),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/project_file_resource.yaml", description: "File list")]
    #[ResponseDefault()]

    public function index(ProjectFileRequest $request, Project $project): JsonResponse
    {
        $query = ProjectFile::with(['fileCategory', 'uploadedByUser'])
            ->where('project_id', $project->id)
            ->where('file_status', 'ACTIVE')
            ->orderByDesc('uploaded_at');

        $filter = $request->input('filter', []);

        if (!empty($filter['file_type'])) {
            $query->where('file_type', $filter['file_type']);
        }
        if (!empty($filter['file_category_id'])) {
            $query->where('file_category_id', $filter['file_category_id']);
        }
        if (!empty($filter['related_entity_type'])) {
            $query->where('related_entity_type', $filter['related_entity_type']);
        }
        if (!empty($filter['related_entity_id'])) {
            $query->where('related_entity_id', $filter['related_entity_id']);
        }

        $files = $query->paginate($request->input('per-page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Files fetched successfully',
            'data' => ProjectFileResource::collection($files),
            'meta' => [
                'page' => $files->currentPage(),
                'limit' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    // ─── POST /v1/projects/{project}/files ───────────────────────────────────

    #[OA\Post(
        tags: [FILE_TAG],
        path: "/v1/projects/{project}/files",
        operationId: "ProjectFileController@store",
        summary: "Upload a file to the project repository. IMAGE files require caption and photo_date. Allowed: PM, Admin Proyek.",
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
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new Schema(ref: "schemas/upload_file_request.yaml")
            )
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "File uploaded")]
    #[ResponseDefault()]
    public function store(ProjectFileRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');


        $storagePath = $file->store(BucketFolder . '/project-files/' . $project->id, 's3');

        $projectFile = ProjectFile::create([
            'project_id' => $project->id,
            'related_entity_type' => $data['related_entity_type'] ?? null,
            'related_entity_id' => $data['related_entity_id'] ?? null,
            'file_category_id' => $data['file_category_id'],
            'file_type' => $data['file_type'],
            'original_file_name' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'mime_type' => $file->getMimeType(),
            'caption' => $data['caption'] ?? null,
            'photo_date' => $data['photo_date'] ?? null,
            'note' => $data['note'] ?? null,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'file_status' => 'ACTIVE',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => new ProjectFileResource(
                $projectFile->load(['fileCategory', 'uploadedByUser'])
            ),
        ], 201);
    }

    // ─── GET /v1/files/{projectFile} ─────────────────────────────────────────

    #[OA\Get(
        tags: [FILE_TAG],
        path: "/v1/files/{projectFile}",
        operationId: "ProjectFileController@show",
        summary: "Get file detail. All authenticated users.",
        parameters: [
            new OA\Parameter(
                in: "path",
                name: "projectFile",
                required: true,
                schema: new Schema(type: "string", format: "uuid")
            ),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "File detail")]
    #[ResponseDefault()]
    public function show(ProjectFileRequest $request, ProjectFile $projectFile): JsonResponse
    {
        $projectFile->load(['fileCategory', 'uploadedByUser', 'project']);

        return response()->json([
            'success' => true,
            'message' => 'File fetched successfully',
            'data' => new ProjectFileResource($projectFile),
        ]);
    }

    // ─── DELETE /v1/files/{projectFile} ──────────────────────────────────────

    #[OA\Delete(
        tags: [FILE_TAG],
        path: "/v1/files/{projectFile}",
        operationId: "ProjectFileController@destroy",
        summary: "Archive (soft-delete) a file. Sets file_status to ARCHIVED. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(
                in: "path",
                name: "projectFile",
                required: true,
                schema: new Schema(type: "string", format: "uuid")
            ),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "File archived")]
    #[ResponseDefault()]
    public function destroy(ProjectFileRequest $request, ProjectFile $projectFile): JsonResponse
    {
        $projectFile->update(['file_status' => 'ARCHIVED']);

        return response()->json([
            'success' => true,
            'message' => 'File archived successfully',
        ]);
    }

    // ─── GET /v1/files/{projectFile}/download ─────────────────────────────────

    public function download(ProjectFileRequest $request, ProjectFile $projectFile): Response
    {
        $diskname = Str::startsWith($projectFile->storage_path, "pmo") ? "s3" : "local";
        $isExists = Storage::disk($diskname)->exists($projectFile->storage_path);
        abort_unless($isExists, 404, 'File not found on disk.' . $diskname);


        return response(Storage::disk($diskname)->get($projectFile->storage_path), 200, [
            'Content-Type' => $projectFile->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $projectFile->original_file_name . '"',
        ]);
    }
}
