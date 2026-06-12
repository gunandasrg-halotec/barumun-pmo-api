<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WbdVersion;
use App\Services\WbdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WbdVersionController extends Controller
{
    public function __construct(private WbdService $wbdService) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $versions = $project->wbdVersions()
            ->with(['submittedByUser', 'approvedByUser', 'rejectedByUser'])
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'WBD versions fetched successfully',
            'data' => $versions->map(fn ($v) => $this->formatVersion($v)),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->canManageWbd()) {
            abort(403, 'You do not have permission to create WBD versions.');
        }

        $request->validate([
            'based_on_version_id' => ['nullable', 'uuid', 'exists:wbd_versions,id'],
        ]);

        $version = $this->wbdService->createDraftVersion(
            $project,
            $request->based_on_version_id,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'WBD draft version created successfully',
            'data' => $this->formatVersion($version->load(['submittedByUser', 'approvedByUser'])),
        ], 201);
    }

    public function show(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        $wbdVersion->load(['submittedByUser', 'approvedByUser', 'rejectedByUser', 'project']);

        return response()->json([
            'success' => true,
            'message' => 'WBD version detail fetched successfully',
            'data' => $this->formatVersion($wbdVersion, true),
        ]);
    }

    public function submit(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        if (!$request->user()->canManageWbd()) {
            abort(403, 'You do not have permission to submit WBD for approval.');
        }

        $version = $this->wbdService->submitForApproval($wbdVersion, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'WBD version submitted for Direksi approval',
            'data' => $this->formatVersion($version->load(['submittedByUser'])),
        ]);
    }

    public function approve(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        if (!$request->user()->canApproveWbd()) {
            abort(403, 'Only Direksi can approve WBD versions.');
        }

        $version = $this->wbdService->approveVersion($wbdVersion, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'WBD version approved and set as active baseline',
            'data' => $this->formatVersion($version->load(['approvedByUser'])),
        ]);
    }

    public function reject(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        if (!$request->user()->canApproveWbd()) {
            abort(403, 'Only Direksi can reject WBD versions.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $version = $this->wbdService->rejectVersion(
            $wbdVersion,
            $request->user()->id,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'WBD version rejected',
            'data' => $this->formatVersion($version->load(['rejectedByUser'])),
        ]);
    }

    private function formatVersion(WbdVersion $version, bool $detail = false): array
    {
        $data = [
            'id' => $version->id,
            'project_id' => $version->project_id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'is_active' => $version->is_active,
            'based_on_version_id' => $version->based_on_version_id,
            'submitted_by' => $version->submittedByUser ? [
                'id' => $version->submittedByUser->id,
                'full_name' => $version->submittedByUser->full_name,
            ] : null,
            'submitted_at' => $version->submitted_at,
            'approved_by' => $version->approvedByUser ? [
                'id' => $version->approvedByUser->id,
                'full_name' => $version->approvedByUser->full_name,
            ] : null,
            'approved_at' => $version->approved_at,
            'rejected_by' => $version->rejectedByUser ? [
                'id' => $version->rejectedByUser->id,
                'full_name' => $version->rejectedByUser->full_name,
            ] : null,
            'rejected_at' => $version->rejected_at,
            'rejection_reason' => $version->rejection_reason,
            'created_at' => $version->created_at,
        ];

        return $data;
    }
}
