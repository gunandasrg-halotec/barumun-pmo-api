<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectFileController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $query = ProjectFile::with(['fileCategory', 'uploadedByUser'])
            ->where('project_id', $project->id)
            ->where('file_status', 'ACTIVE')
            ->orderByDesc('uploaded_at');

        if ($request->filled('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        if ($request->filled('file_category_id')) {
            $query->where('file_category_id', $request->file_category_id);
        }

        if ($request->filled('related_entity_type')) {
            $query->where('related_entity_type', $request->related_entity_type);
        }

        if ($request->filled('related_entity_id')) {
            $query->where('related_entity_id', $request->related_entity_id);
        }

        $files = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Files fetched successfully',
            'data' => $files->map(fn ($f) => $this->formatFile($f)),
            'meta' => [
                'page' => $files->currentPage(),
                'limit' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->canManageFiles()) {
            abort(403, 'You do not have permission to upload files.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20MB
            'file_category_id' => ['required', 'uuid', 'exists:file_categories,id'],
            'file_type' => ['required', Rule::in(['DOCUMENT', 'IMAGE'])],
            'related_entity_type' => ['nullable', Rule::in(['WBD_NODE', 'PROGRESS_ENTRY'])],
            'related_entity_id' => ['nullable', 'uuid'],
            'caption' => ['nullable', 'string', 'max:500'],
            'photo_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        // Business rule: IMAGE requires caption and photo_date
        if ($validated['file_type'] === 'IMAGE') {
            $request->validate([
                'caption' => ['required', 'string', 'max:500'],
                'photo_date' => ['required', 'date'],
            ]);
        }

        // Business rule: related_entity_type requires related_entity_id
        if (!empty($validated['related_entity_type'])) {
            $request->validate([
                'related_entity_id' => ['required', 'uuid'],
            ]);
        }

        $file = $request->file('file');
        $path = $file->store('project-files/' . $project->id, 'local');

        $projectFile = ProjectFile::create([
            'project_id' => $project->id,
            'related_entity_type' => $validated['related_entity_type'] ?? null,
            'related_entity_id' => $validated['related_entity_id'] ?? null,
            'file_category_id' => $validated['file_category_id'],
            'file_type' => $validated['file_type'],
            'original_file_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'caption' => $validated['caption'] ?? null,
            'photo_date' => $validated['photo_date'] ?? null,
            'note' => $validated['note'] ?? null,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'file_status' => 'ACTIVE',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => $this->formatFile($projectFile->load(['fileCategory', 'uploadedByUser'])),
        ], 201);
    }

    public function show(Request $request, ProjectFile $projectFile): JsonResponse
    {
        $projectFile->load(['fileCategory', 'uploadedByUser', 'project']);

        return response()->json([
            'success' => true,
            'message' => 'File fetched successfully',
            'data' => $this->formatFile($projectFile, true),
        ]);
    }

    public function destroy(Request $request, ProjectFile $projectFile): JsonResponse
    {
        if (!$request->user()->canManageFiles()) {
            abort(403, 'You do not have permission to manage files.');
        }

        // Soft-archive instead of hard delete
        $projectFile->update(['file_status' => 'ARCHIVED']);

        return response()->json([
            'success' => true,
            'message' => 'File archived successfully',
        ]);
    }

    private function formatFile(ProjectFile $file, bool $detail = false): array
    {
        $data = [
            'id' => $file->id,
            'project_id' => $file->project_id,
            'file_type' => $file->file_type,
            'original_file_name' => $file->original_file_name,
            'mime_type' => $file->mime_type,
            'caption' => $file->caption,
            'photo_date' => $file->photo_date?->toDateString(),
            'note' => $file->note,
            'related_entity_type' => $file->related_entity_type,
            'related_entity_id' => $file->related_entity_id,
            'file_status' => $file->file_status,
            'file_category' => $file->fileCategory ? [
                'id' => $file->fileCategory->id,
                'name' => $file->fileCategory->category_name,
            ] : null,
            'uploaded_by' => $file->uploadedByUser ? [
                'id' => $file->uploadedByUser->id,
                'full_name' => $file->uploadedByUser->full_name,
            ] : null,
            'uploaded_at' => $file->uploaded_at,
        ];

        return $data;
    }
}
