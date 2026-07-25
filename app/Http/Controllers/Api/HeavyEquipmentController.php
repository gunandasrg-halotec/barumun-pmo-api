<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeavyEquipmentRequest;
use App\Http\Resources\HeavyEquipmentResource;
use App\Models\HeavyEquipment;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class HeavyEquipmentController extends Controller
{
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_TAG],
        path: "/v1/heavy-equipment",
        operationId: "HeavyEquipmentController@index",
        summary: "List master alat berat. ?active_only=1 untuk yang aktif saja.",
        parameters: [
            new OA\Parameter(in: "query", name: "active_only", schema: new Schema(type: "integer", enum: [0, 1])),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Heavy equipment list")]
    #[ResponseDefault()]
    public function index(HeavyEquipmentRequest $request): JsonResponse
    {
        $query = HeavyEquipment::orderBy('code');

        if (filter_var($request->get('active_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Heavy equipment fetched successfully',
            'data'    => HeavyEquipmentResource::collection($query->get()),
        ]);
    }

    #[OA\Post(
        tags: [HEAVY_EQUIPMENT_TAG],
        path: "/v1/heavy-equipment",
        operationId: "HeavyEquipmentController@store",
        summary: "Buat master alat berat. Hanya Administrator Sistem.",
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Heavy equipment created")]
    #[ResponseDefault()]
    public function store(HeavyEquipmentRequest $request): JsonResponse
    {
        $equipment = HeavyEquipment::create([
            'code'      => $request->validated('code'),
            'type'      => $request->validated('type'),
            'brand'     => $request->validated('brand'),
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Heavy equipment created successfully',
            'data'    => new HeavyEquipmentResource($equipment),
        ], 201);
    }

    #[OA\Patch(
        tags: [HEAVY_EQUIPMENT_TAG],
        path: "/v1/heavy-equipment/{heavyEquipment}",
        operationId: "HeavyEquipmentController@update",
        summary: "Ubah master alat berat. Hanya Administrator Sistem.",
        parameters: [
            new OA\Parameter(in: "path", name: "heavyEquipment", required: true, schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Heavy equipment updated")]
    #[ResponseDefault()]
    public function update(HeavyEquipmentRequest $request, HeavyEquipment $heavyEquipment): JsonResponse
    {
        $heavyEquipment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Heavy equipment updated successfully',
            'data'    => new HeavyEquipmentResource($heavyEquipment->fresh()),
        ]);
    }
}
