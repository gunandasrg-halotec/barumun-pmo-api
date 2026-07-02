<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

/**
 * Phase 10 — Audit Log Viewer.
 *
 * Read-only. Restricted to Administrator Sistem.
 * Provides global log viewer and per-entity log viewer.
 */
class AuditLogController extends Controller
{
    // ─── GET /v1/audit-logs ──────────────────────────────────────────────────

    #[GetIndexRequest(
        tags: [AUDIT_TAG],
        path: "/v1/audit-logs",
        operationId: "AuditLogController@index",
        summary: "List all audit logs with filters. Restricted to Administrator Sistem.",
        security: [Auth_JWT],
        filters: [
            new OA\Parameter(in: "query", name: "filter[entity_type]",
                description: "Filter by entity type (e.g. wbd_version, progress_entry)",
                schema: new Schema(type: "string")),
            new OA\Parameter(in: "query", name: "filter[action_type]",
                description: "Filter by action type (CREATE, UPDATE, APPROVE, REJECT, SUBMIT)",
                schema: new Schema(type: "string",
                    enum: ["CREATE", "UPDATE", "APPROVE", "REJECT", "SUBMIT"])),
            new OA\Parameter(in: "query", name: "filter[action_by]",
                description: "Filter by user UUID",
                schema: new Schema(type: "string", format: "uuid")),
            new OA\Parameter(in: "query", name: "filter[date_from]",
                description: "From date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "filter[date_to]",
                description: "To date (YYYY-MM-DD)",
                schema: new Schema(type: "string", format: "date")),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/audit_log_resource.yaml", description: "Audit log list")]
    #[ResponseDefault()]
    public function index(AuditLogRequest $request): JsonResponse
    {
        $query  = AuditLog::with(['actionByUser.role'])->orderByDesc('action_at');
        $filter = $request->input('filter', []);

        if (!empty($filter['entity_type'])) {
            $query->where('entity_type', $filter['entity_type']);
        }
        if (!empty($filter['action_type'])) {
            $query->where('action_type', $filter['action_type']);
        }
        if (!empty($filter['action_by'])) {
            $query->where('action_by', $filter['action_by']);
        }
        if (!empty($filter['date_from'])) {
            $query->whereDate('action_at', '>=', $filter['date_from']);
        }
        if (!empty($filter['date_to'])) {
            $query->whereDate('action_at', '<=', $filter['date_to']);
        }

        $logs = $query->paginate($request->get('per-page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Audit logs fetched successfully',
            'data'    => AuditLogResource::collection($logs),
            'meta'    => [
                'page'  => $logs->currentPage(),
                'limit' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    // ─── GET /v1/audit-logs/{entityType}/{entityId} ───────────────────────────

    #[OA\Get(
        tags: [AUDIT_TAG],
        path: "/v1/audit-logs/{entityType}/{entityId}",
        operationId: "AuditLogController@byEntity",
        summary: "Get audit trail for a specific entity (e.g. all actions on a WBD version). Restricted to Administrator Sistem.",
        parameters: [
            new OA\Parameter(in: "path", name: "entityType", required: true,
                schema: new Schema(type: "string"),
                description: "Entity type, e.g. wbd_version, progress_entry, actual_cost_transaction"),
            new OA\Parameter(in: "path", name: "entityId", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Audit trail for the specified entity")]
    #[ResponseDefault()]
    public function byEntity(AuditLogRequest $request, string $entityType, string $entityId): JsonResponse
    {
        $logs = AuditLog::with(['actionByUser.role'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('action_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Audit trail for {$entityType} {$entityId}",
            'data'    => AuditLogResource::collection($logs),
        ]);
    }

    // ─── GET /v1/audit-logs/entity-types ─────────────────────────────────────

    #[OA\Get(
        tags: [AUDIT_TAG],
        path: "/v1/audit-logs/entity-types",
        operationId: "AuditLogController@entityTypes",
        summary: "List all distinct entity types in the audit log. Useful for building filter dropdowns.",
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Distinct entity types")]
    #[ResponseDefault()]
    public function entityTypes(AuditLogRequest $request): JsonResponse
    {
        $types = AuditLog::distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type');

        return response()->json([
            'success' => true,
            'message' => 'Entity types fetched successfully',
            'data'    => $types,
        ]);
    }
}
