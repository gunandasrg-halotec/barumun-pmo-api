<?php

use App\Http\Controllers\Api\ActualCostController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileCategoryController;
use App\Http\Controllers\Api\GanttController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WbdNodeController;
use App\Http\Controllers\Api\WbdVersionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes require JSON response. Authentication via Laravel Sanctum token.
|
*/

// Public routes


// Protected routes
Route::middleware('jwtAuth')->group(function () {

    
    
    // Users & Roles (Admin Sistem only)
    
    Route::get('/v1/roles', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Role::orderBy('role_name')->get()->map(fn ($r) => [
                'id' => $r->id,
                'role_name' => $r->role_name,
                'description' => $r->description,
            ]),
        ]);
    });

    // File Categories (public read, Admin Sistem write)
    Route::get('/v1/file-categories', [FileCategoryController::class, 'index']);
    Route::post('/v1/file-categories', [FileCategoryController::class, 'store']);
    Route::patch('/v1/file-categories/{fileCategory}', [FileCategoryController::class, 'update']);

    // Projects (all users can read, PM/Admin Proyek can write)
    Route::get('/v1/projects', [ProjectController::class, 'index']);
    Route::post('/v1/projects', [ProjectController::class, 'store']);
    Route::get('/v1/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/v1/projects/{project}', [ProjectController::class, 'update']);

    // WBD Versions
    Route::get('/v1/projects/{project}/wbd-versions', [WbdVersionController::class, 'index']);
    Route::post('/v1/projects/{project}/wbd-versions', [WbdVersionController::class, 'store']);

    // Global pending WBD approvals (Direksi) — must be BEFORE parameterized routes
    Route::get('/v1/wbd-versions/pending', function () {
        $versions = \App\Models\WbdVersion::with(['project', 'submittedByUser'])
            ->where('status', \App\Enums\WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value)
            ->orderByDesc('updated_at')
            ->get();
        return response()->json(['success' => true, 'data' => $versions]);
    });

    Route::get('/v1/wbd-versions/{wbdVersion}', [WbdVersionController::class, 'show']);
    Route::post('/v1/wbd-versions/{wbdVersion}/submit', [WbdVersionController::class, 'submit']);
    Route::post('/v1/wbd-versions/{wbdVersion}/approve', [WbdVersionController::class, 'approve']);
    Route::post('/v1/wbd-versions/{wbdVersion}/reject', [WbdVersionController::class, 'reject']);

    // WBD Nodes
    Route::get('/v1/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'index']);
    Route::post('/v1/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'store']);
    Route::patch('/v1/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'update']);
    Route::delete('/v1/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'destroy']);

    // Progress Entries
    Route::get('/v1/projects/{project}/progress-entries', [ProgressController::class, 'index']);
    Route::post('/v1/projects/{project}/progress-entries', [ProgressController::class, 'store']);
    Route::get('/v1/progress-entries/{progressEntry}', [ProgressController::class, 'show']);
    Route::post('/v1/progress-entries/{progressEntry}/approve', [ProgressController::class, 'approve']);
    Route::post('/v1/progress-entries/{progressEntry}/reject', [ProgressController::class, 'reject']);

    // Actual Cost Transactions
    Route::get('/v1/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'index']);
    Route::post('/v1/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'store']);
    Route::get('/v1/actual-cost-transactions/{actualCostTransaction}', [ActualCostController::class, 'show']);
    Route::post('/v1/actual-cost-transactions/{actualCostTransaction}/approve', [ActualCostController::class, 'approve']);
    Route::post('/v1/actual-cost-transactions/{actualCostTransaction}/reject', [ActualCostController::class, 'reject']);

    // Files
    Route::get('/v1/projects/{project}/files', [ProjectFileController::class, 'index']);
    Route::post('/v1/projects/{project}/files', [ProjectFileController::class, 'store']);
    Route::get('/v1/files/{projectFile}', [ProjectFileController::class, 'show']);
    Route::delete('/v1/files/{projectFile}', [ProjectFileController::class, 'destroy']);

    // Analytics (read-only, all roles)
    Route::get('/v1/projects/{project}/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/v1/projects/{project}/gantt', [GanttController::class, 'index']);
    Route::get('/v1/projects/{project}/s-curve', [AnalyticsController::class, 'sCurve']);
    Route::get('/v1/projects/{project}/cost-analysis', [AnalyticsController::class, 'costAnalysis']);

    // Reports
    Route::get('/v1/projects/{project}/reports', [ReportController::class, 'index']);
    Route::post('/v1/projects/{project}/reports/generate', [ReportController::class, 'generate']);
    Route::get('/v1/reports/{reportRecord}', [ReportController::class, 'show']);

    // Audit Logs
    Route::get('/v1/audit-logs', function (\Illuminate\Http\Request $request) {
        $logs = \App\Models\AuditLog::with('actionByUser')
            ->orderByDesc('action_at')
            ->paginate($request->get('limit', 50));
        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => ['page' => $logs->currentPage(), 'total' => $logs->total()],
        ]);
    });

    Route::get('/v1/audit-logs/{entityType}/{entityId}', function (string $entityType, string $entityId) {
        $logs = \App\Models\AuditLog::with('actionByUser')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('action_at')
            ->get();
        return response()->json(['success' => true, 'data' => $logs]);
    });
});
