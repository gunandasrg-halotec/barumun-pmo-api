<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeavyEquipmentActivityTypeRequest;
use App\Http\Resources\HeavyEquipmentActivityTypeResource;
use App\Models\HeavyEquipmentActivityType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class HeavyEquipmentActivityTypeController extends Controller
{
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_ACTIVITY_TYPE_TAG],
        path: "/v1/heavy-equipment/activity-types",
        operationId: "HeavyEquipmentActivityTypeController@index",
        summary: "List jenis pekerjaan. ?active_only=1 untuk yang aktif saja.",
        parameters: [
            new OA\Parameter(in: "query", name: "active_only", schema: new Schema(type: "integer", enum: [0, 1])),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Activity type list")]
    #[ResponseDefault()]
    public function index(HeavyEquipmentActivityTypeRequest $request): JsonResponse
    {
        $query = HeavyEquipmentActivityType::orderBy('sort_order')->orderBy('name');

        if (filter_var($request->get('active_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity types fetched successfully',
            'data'    => HeavyEquipmentActivityTypeResource::collection($query->get()),
        ]);
    }

    #[OA\Post(
        tags: [HEAVY_EQUIPMENT_ACTIVITY_TYPE_TAG],
        path: "/v1/heavy-equipment/activity-types",
        operationId: "HeavyEquipmentActivityTypeController@store",
        summary: "Tambah jenis pekerjaan. Hanya Admin.",
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Activity type created")]
    #[ResponseDefault()]
    public function store(HeavyEquipmentActivityTypeRequest $request): JsonResponse
    {
        $item = HeavyEquipmentActivityType::create([
            'code'             => $request->validated('code'),
            'name'             => $request->validated('name'),
            'unit'             => $request->validated('unit'),
            'allow_date_range' => $request->validated('allow_date_range') ?? false,
            'sort_order'       => $request->validated('sort_order') ?? 0,
            'is_active'        => $request->validated('is_active') ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity type created successfully',
            'data'    => new HeavyEquipmentActivityTypeResource($item),
        ], 201);
    }

    #[OA\Patch(
        tags: [HEAVY_EQUIPMENT_ACTIVITY_TYPE_TAG],
        path: "/v1/heavy-equipment/activity-types/{activity_type}",
        operationId: "HeavyEquipmentActivityTypeController@update",
        summary: "Ubah jenis pekerjaan. Hanya Admin.",
        parameters: [
            new OA\Parameter(in: "path", name: "activity_type", required: true, schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Activity type updated")]
    #[ResponseDefault()]
    public function update(HeavyEquipmentActivityTypeRequest $request, HeavyEquipmentActivityType $activity_type): JsonResponse
    {
        $activity_type->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Activity type updated successfully',
            'data'    => new HeavyEquipmentActivityTypeResource($activity_type->fresh()),
        ]);
    }
}
