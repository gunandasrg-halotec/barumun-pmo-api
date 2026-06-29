<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WbdVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetSubmissionsController extends Controller
{
    // POST /v1/projects/{project}/reset-submissions
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->hasRole(RoleName::DIREKSI)) {
            return response()->json(['message' => 'Hanya Direksi yang dapat mereset pengajuan.'], 403);
        }

        $affected = DB::transaction(function () use ($project) {
            // Change all PENDING_DIRECTOR_APPROVAL versions → DRAFT
            $count = WbdVersion::where('project_id', $project->id)
                ->where('status', 'PENDING_DIRECTOR_APPROVAL')
                ->update(['status' => 'DRAFT']);

            // Record the reset timestamp so the frontend can zero out the submission counter
            $project->update(['submissions_reset_at' => now()]);

            return $count;
        });

        return response()->json([
            'success' => true,
            'message' => "Reset pengajuan berhasil. {$affected} WBD dikembalikan ke status DRAFT.",
        ]);
    }
}
