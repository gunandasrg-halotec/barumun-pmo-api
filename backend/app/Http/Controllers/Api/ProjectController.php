<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::with(['activeWbdVersion', 'createdBy.role'])
            ->orderBy('project_name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('project_name', 'like', '%' . $request->search . '%')
                    ->orWhere('project_code', 'like', '%' . $request->search . '%')
                    ->orWhere('client_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Projects fetched successfully',
            'data' => $projects->map(fn ($p) => $this->formatProject($p)),
            'meta' => [
                'page' => $projects->currentPage(),
                'limit' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $project->load(['activeWbdVersion', 'createdBy.role']);

        return response()->json([
            'success' => true,
            'message' => 'Project detail fetched successfully',
            'data' => $this->formatProject($project, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Project creation: PM or Admin Proyek can create projects
        if (!$request->user()->isProjectManager() && !$request->user()->isAdminProyek() && !$request->user()->isAdministratorSistem()) {
            abort(403, 'You do not have permission to create projects.');
        }

        $validated = $request->validate([
            'project_code' => ['required', 'string', 'max:100', 'unique:projects,project_code'],
            'project_name' => ['required', 'string', 'min:3', 'max:200'],
            'client_name' => ['required', 'string', 'min:2', 'max:200'],
            'location' => ['required', 'string', 'min:2', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'gte:start_date'],
            'status' => ['sometimes', 'string', Rule::in(['ACTIVE', 'COMPLETED', 'ON_HOLD', 'CANCELLED'])],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            ...$validated,
            'status' => $validated['status'] ?? 'ACTIVE',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data' => $this->formatProject($project->load(['activeWbdVersion', 'createdBy.role'])),
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->isProjectManager() && !$request->user()->isAdminProyek() && !$request->user()->isAdministratorSistem()) {
            abort(403, 'You do not have permission to update projects.');
        }

        $validated = $request->validate([
            'project_name' => ['sometimes', 'string', 'min:3', 'max:200'],
            'client_name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'location' => ['sometimes', 'string', 'min:2', 'max:200'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'gte:start_date'],
            'status' => ['sometimes', 'string', Rule::in(['ACTIVE', 'COMPLETED', 'ON_HOLD', 'CANCELLED'])],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => $this->formatProject($project->fresh(['activeWbdVersion', 'createdBy.role'])),
        ]);
    }

    private function formatProject(Project $project, bool $detail = false): array
    {
        $data = [
            'id' => $project->id,
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'client_name' => $project->client_name,
            'location' => $project->location,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'status' => $project->status,
            'has_active_baseline' => $project->hasActiveBaseline(),
            'active_wbd_version' => $project->activeWbdVersion ? [
                'id' => $project->activeWbdVersion->id,
                'version_number' => $project->activeWbdVersion->version_number,
                'status' => $project->activeWbdVersion->status,
            ] : null,
        ];

        if ($detail) {
            $data['description'] = $project->description;
            $data['created_by'] = $project->createdBy ? [
                'id' => $project->createdBy->id,
                'full_name' => $project->createdBy->full_name,
            ] : null;
            $data['created_at'] = $project->created_at;
            $data['updated_at'] = $project->updated_at;
        }

        return $data;
    }
}
