<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileCategoryRequest;
use App\Http\Resources\FileCategoryResource;
use App\Models\FileCategory;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class FileCategoryController extends Controller
{
    // ─── GET /v1/file-categories ─────────────────────────────────────────────

    #[OA\Get(
        tags: [FILE_CATEGORY_TAG],
        path: "/v1/file-categories",
        operationId: "FileCategoryController@index",
        summary: "List file categories. Pass ?active_only=1 to get only active categories.",
        parameters: [
            new OA\Parameter(in: "query", name: "active_only",
                description: "1 = active categories only",
                schema: new Schema(type: "integer", enum: [0, 1])),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "File category list")]
    #[ResponseDefault()]
    public function index(FileCategoryRequest $request): JsonResponse
    {
        $query = FileCategory::orderBy('category_name');

        if (filter_var($request->get('active_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'File categories fetched successfully',
            'data'    => FileCategoryResource::collection($query->get()),
        ]);
    }

    // ─── POST /v1/file-categories ─────────────────────────────────────────────

    #[OA\Post(
        tags: [FILE_CATEGORY_TAG],
        path: "/v1/file-categories",
        operationId: "FileCategoryController@store",
        summary: "Create a new file category. Allowed: Administrator Sistem only.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/new_file_category_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "File category created")]
    #[ResponseDefault()]
    public function store(FileCategoryRequest $request): JsonResponse
    {
        $category = FileCategory::create([
            'category_name' => $request->validated('category_name'),
            'description'   => $request->validated('description'),
            'is_active'     => $request->validated('is_active') ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File category created successfully',
            'data'    => new FileCategoryResource($category),
        ], 201);
    }

    // ─── PATCH /v1/file-categories/{fileCategory} ────────────────────────────

    #[OA\Patch(
        tags: [FILE_CATEGORY_TAG],
        path: "/v1/file-categories/{fileCategory}",
        operationId: "FileCategoryController@update",
        summary: "Update a file category (name, description, active status). Allowed: Administrator Sistem only.",
        parameters: [
            new OA\Parameter(in: "path", name: "fileCategory", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/update_file_category_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "File category updated")]
    #[ResponseDefault()]
    public function update(FileCategoryRequest $request, FileCategory $fileCategory): JsonResponse
    {
        $fileCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'File category updated successfully',
            'data'    => new FileCategoryResource($fileCategory->fresh()),
        ]);
    }
}
