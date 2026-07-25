<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Resources\HeavyEquipmentLogResource;
use App\Models\HeavyEquipmentLog;
use App\Models\HeavyEquipmentLogPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class HeavyEquipmentLogController extends Controller
{
    // ─── GET /v1/heavy-equipment/logs (raw data) ─────────────────────────────
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_LOG_TAG],
        path: "/v1/heavy-equipment/logs",
        operationId: "HeavyEquipmentLogController@index",
        summary: "Data mentah laporan harian alat berat (paginated, filterable).",
        parameters: [
            new OA\Parameter(in: "query", name: "equipment_id", schema: new Schema(type: "string", format: "uuid")),
            new OA\Parameter(in: "query", name: "kebun", schema: new Schema(type: "string")),
            new OA\Parameter(in: "query", name: "date_from", schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "date_to", schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "page", schema: new Schema(type: "integer")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Log list")]
    #[ResponseDefault()]
    public function index(Request $request): JsonResponse
    {
        $query = HeavyEquipmentLog::with(['equipment', 'activities', 'costs.costItem', 'photos'])
            ->when($request->get('equipment_id'), fn ($q, $v) => $q->where('heavy_equipment_id', $v))
            ->when($request->get('kebun'), fn ($q, $v) => $q->where('kebun', $v))
            ->when($request->get('date_from'), fn ($q, $v) => $q->whereDate('log_date', '>=', $v))
            ->when($request->get('date_to'), fn ($q, $v) => $q->whereDate('log_date', '<=', $v))
            ->orderByDesc('log_date')
            ->orderByDesc('created_at');

        $logs = $query->paginate($request->input('per-page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Logs fetched successfully',
            'data'    => HeavyEquipmentLogResource::collection($logs),
            'meta'    => [
                'page'  => $logs->currentPage(),
                'limit' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    // ─── GET /v1/heavy-equipment/logs/{log} ──────────────────────────────────
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_LOG_TAG],
        path: "/v1/heavy-equipment/logs/{log}",
        operationId: "HeavyEquipmentLogController@show",
        summary: "Detail satu laporan harian (semua field + pekerjaan + biaya + foto).",
        parameters: [
            new OA\Parameter(in: "path", name: "log", required: true, schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Log detail")]
    #[ResponseDefault()]
    public function show(HeavyEquipmentLog $log): JsonResponse
    {
        $log->load(['equipment', 'activities', 'costs.costItem', 'photos']);

        return response()->json([
            'success' => true,
            'message' => 'Log fetched successfully',
            'data'    => new HeavyEquipmentLogResource($log),
        ]);
    }

    // ─── GET /v1/heavy-equipment/logs/{log}/photos/{photo} ────────────────────
    public function downloadPhoto(HeavyEquipmentLog $log, HeavyEquipmentLogPhoto $photo): Response
    {
        abort_unless($photo->heavy_equipment_log_id === $log->id, 404, 'Photo not found for this log.');

        $diskname = Str::startsWith($photo->storage_path, 'pmo') ? 's3' : 'local';
        abort_unless(Storage::disk($diskname)->exists($photo->storage_path), 404, 'File not found on disk.');

        return response(Storage::disk($diskname)->get($photo->storage_path), 200, [
            'Content-Type'        => $photo->mime_type,
            'Content-Disposition' => 'inline; filename="' . $photo->original_file_name . '"',
        ]);
    }
}
