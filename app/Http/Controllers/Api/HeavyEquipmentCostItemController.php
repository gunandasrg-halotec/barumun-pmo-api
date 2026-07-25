<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeavyEquipmentCostItemRequest;
use App\Http\Resources\HeavyEquipmentCostItemResource;
use App\Models\HeavyEquipmentCostItem;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class HeavyEquipmentCostItemController extends Controller
{
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_COST_TAG],
        path: "/v1/heavy-equipment/cost-items",
        operationId: "HeavyEquipmentCostItemController@index",
        summary: "List katalog item biaya. ?active_only=1 untuk yang aktif saja.",
        parameters: [
            new OA\Parameter(in: "query", name: "active_only", schema: new Schema(type: "integer", enum: [0, 1])),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Cost item list")]
    #[ResponseDefault()]
    public function index(HeavyEquipmentCostItemRequest $request): JsonResponse
    {
        $query = HeavyEquipmentCostItem::orderBy('sort_order')->orderBy('name');

        if (filter_var($request->get('active_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cost items fetched successfully',
            'data'    => HeavyEquipmentCostItemResource::collection($query->get()),
        ]);
    }

    #[OA\Post(
        tags: [HEAVY_EQUIPMENT_COST_TAG],
        path: "/v1/heavy-equipment/cost-items",
        operationId: "HeavyEquipmentCostItemController@store",
        summary: "Tambah item biaya. Hanya Finance / Administrator Sistem.",
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Cost item created")]
    #[ResponseDefault()]
    public function store(HeavyEquipmentCostItemRequest $request): JsonResponse
    {
        $item = HeavyEquipmentCostItem::create([
            'name'           => $request->validated('name'),
            'default_amount' => $request->validated('default_amount'),
            'is_active'      => $request->validated('is_active') ?? true,
            'sort_order'     => $request->validated('sort_order') ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cost item created successfully',
            'data'    => new HeavyEquipmentCostItemResource($item),
        ], 201);
    }

    #[OA\Patch(
        tags: [HEAVY_EQUIPMENT_COST_TAG],
        path: "/v1/heavy-equipment/cost-items/{costItem}",
        operationId: "HeavyEquipmentCostItemController@update",
        summary: "Ubah item biaya. Hanya Finance / Administrator Sistem.",
        parameters: [
            new OA\Parameter(in: "path", name: "costItem", required: true, schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Cost item updated")]
    #[ResponseDefault()]
    public function update(HeavyEquipmentCostItemRequest $request, HeavyEquipmentCostItem $costItem): JsonResponse
    {
        $costItem->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cost item updated successfully',
            'data'    => new HeavyEquipmentCostItemResource($costItem->fresh()),
        ]);
    }
}
