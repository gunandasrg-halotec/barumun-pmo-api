<?php

use App\Http\Controllers\Api\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Audit Log Routes — Phase 10: Audit & Integrity
|--------------------------------------------------------------------------
|
| Read-only. Restricted to Administrator Sistem.
|
| IMPORTANT: /entity-types must be declared BEFORE /{entityType}/{entityId}
| to prevent Laravel matching "entity-types" as the entityType parameter.
|
*/

Route::middleware('jwtAuth')->group(function () {

    // Global audit log list (filterable)
    Route::get('/v1/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-log.index');

    // Distinct entity types — used for filter dropdowns
    Route::get('/v1/audit-logs/entity-types', [AuditLogController::class, 'entityTypes'])
        ->name('audit-log.entity-types');

    // Audit trail for a specific entity
    Route::get('/v1/audit-logs/{entityType}/{entityId}', [AuditLogController::class, 'byEntity'])
        ->name('audit-log.by-entity');
});
