<?php

use App\Http\Controllers\Api\ActualCostController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileCategoryController;
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
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Users & Roles (Admin Sistem only)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);

    Route::get('/roles', function () {
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
    Route::get('/file-categories', [FileCategoryController::class, 'index']);
    Route::post('/file-categories', [FileCategoryController::class, 'store']);
    Route::patch('/file-categories/{fileCategory}', [FileCategoryController::class, 'update']);

    // Projects (all users can read, PM/Admin Proyek can write)
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);

    // WBD Versions
    Route::get('/projects/{project}/wbd-versions', [WbdVersionController::class, 'index']);
    Route::post('/projects/{project}/wbd-versions', [WbdVersionController::class, 'store']);

    // Global pending WBD approvals (Direksi) — must be BEFORE parameterized routes
    Route::get('/wbd-versions/pending', function () {
        $versions = \App\Models\WbdVersion::with(['project', 'submittedByUser'])
            ->where('status', \App\Enums\WbdVersionStatus::PENDING_DIRECTOR_APPROVAL->value)
            ->orderByDesc('updated_at')
            ->get();
        return response()->json(['success' => true, 'data' => $versions]);
    });

    Route::get('/wbd-versions/{wbdVersion}', [WbdVersionController::class, 'show']);
    Route::post('/wbd-versions/{wbdVersion}/submit', [WbdVersionController::class, 'submit']);
    Route::post('/wbd-versions/{wbdVersion}/approve', [WbdVersionController::class, 'approve']);
    Route::post('/wbd-versions/{wbdVersion}/reject', [WbdVersionController::class, 'reject']);

    // WBD Nodes
    Route::get('/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'index']);
    Route::post('/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'store']);
    Route::patch('/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'update']);
    Route::delete('/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'destroy']);

    // Progress Entries
    Route::get('/projects/{project}/progress-entries', [ProgressController::class, 'index']);
    Route::post('/projects/{project}/progress-entries', [ProgressController::class, 'store']);
    Route::get('/progress-entries/{progressEntry}', [ProgressController::class, 'show']);
    Route::post('/progress-entries/{progressEntry}/approve', [ProgressController::class, 'approve']);
    Route::post('/progress-entries/{progressEntry}/reject', [ProgressController::class, 'reject']);

    // Actual Cost Transactions
    Route::get('/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'index']);
    Route::post('/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'store']);
    Route::get('/actual-cost-transactions/{actualCostTransaction}', [ActualCostController::class, 'show']);
    Route::post('/actual-cost-transactions/{actualCostTransaction}/approve', [ActualCostController::class, 'approve']);
    Route::post('/actual-cost-transactions/{actualCostTransaction}/reject', [ActualCostController::class, 'reject']);

    // Files
    Route::get('/projects/{project}/files', [ProjectFileController::class, 'index']);
    Route::post('/projects/{project}/files', [ProjectFileController::class, 'store']);
    Route::get('/files/{projectFile}', [ProjectFileController::class, 'show']);
    Route::delete('/files/{projectFile}', [ProjectFileController::class, 'destroy']);

    // Analytics (read-only, all roles)
    Route::get('/projects/{project}/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/projects/{project}/gantt', [AnalyticsController::class, 'gantt']);
    Route::get('/projects/{project}/s-curve', [AnalyticsController::class, 'sCurve']);
    Route::get('/projects/{project}/cost-analysis', [AnalyticsController::class, 'costAnalysis']);

    // Reports
    Route::get('/projects/{project}/reports', [ReportController::class, 'index']);
    Route::post('/projects/{project}/reports/generate', [ReportController::class, 'generate']);
    Route::get('/reports/{reportRecord}', [ReportController::class, 'show']);

    // Audit Logs
    Route::get('/audit-logs', function (\Illuminate\Http\Request $request) {
        $logs = \App\Models\AuditLog::with('actionByUser')
            ->orderByDesc('action_at')
            ->paginate($request->get('limit', 50));
        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => ['page' => $logs->currentPage(), 'total' => $logs->total()],
        ]);
    });

    Route::get('/audit-logs/{entityType}/{entityId}', function (string $entityType, string $entityId) {
        $logs = \App\Models\AuditLog::with('actionByUser')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('action_at')
            ->get();
        return response()->json(['success' => true, 'data' => $logs]);
    });
});
