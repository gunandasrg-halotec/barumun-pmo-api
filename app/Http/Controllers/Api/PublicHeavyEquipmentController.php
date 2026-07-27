<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeavyEquipmentLogRequest;
use App\Http\Resources\HeavyEquipmentActivityTypeResource;
use App\Http\Resources\HeavyEquipmentCostItemResource;
use App\Http\Resources\HeavyEquipmentLogResource;
use App\Http\Resources\HeavyEquipmentResource;
use App\Models\HeavyEquipment;
use App\Models\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentCostItem;
use App\Services\HeavyEquipmentLogService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Endpoint publik (tanpa login) untuk pencatatan di lapangan.
 * Semua route diproteksi middleware `pinAuth` (header X-Access-Pin).
 */
class PublicHeavyEquipmentController extends Controller
{
    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/verify-pin",
        operationId: "PublicHeavyEquipmentController@verifyPin",
        summary: "Validasi kode akses (PIN) via header X-Access-Pin."
    )]
    #[Response2xx(description: "PIN valid")]
    #[ResponseDefault()]
    public function verifyPin(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Kode akses valid']);
    }

    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/equipments",
        operationId: "PublicHeavyEquipmentController@equipments",
        summary: "Daftar alat berat aktif untuk form lapangan."
    )]
    #[Response2xx(description: "Equipment list")]
    #[ResponseDefault()]
    public function equipments(): JsonResponse
    {
        $equipments = HeavyEquipment::where('is_active', true)->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'message' => 'Equipments fetched successfully',
            'data'    => HeavyEquipmentResource::collection($equipments),
        ]);
    }

    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/cost-items",
        operationId: "PublicHeavyEquipmentController@costItems",
        summary: "Daftar item biaya aktif (untuk diisi nominal di lapangan)."
    )]
    #[Response2xx(description: "Cost item list")]
    #[ResponseDefault()]
    public function costItems(): JsonResponse
    {
        $items = HeavyEquipmentCostItem::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Cost items fetched successfully',
            'data'    => HeavyEquipmentCostItemResource::collection($items),
        ]);
    }

    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/activity-types",
        operationId: "PublicHeavyEquipmentController@activityTypes",
        summary: "Daftar jenis pekerjaan (value/label/unit)."
    )]
    #[Response2xx(description: "Activity types")]
    #[ResponseDefault()]
    public function activityTypes(): JsonResponse
    {
        $types = HeavyEquipmentActivityType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Activity types fetched successfully',
            'data'    => HeavyEquipmentActivityTypeResource::collection($types),
        ]);
    }

    #[OA\Post(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/logs",
        operationId: "PublicHeavyEquipmentController@storeLog",
        summary: "Submit laporan harian dari lapangan (multipart: activities[], costs[], photos[])."
    )]
    #[Response2xx(response: "201", description: "Log created")]
    #[ResponseDefault()]
    public function storeLog(HeavyEquipmentLogRequest $request, HeavyEquipmentLogService $service): JsonResponse
    {
        $log = $service->createFromPublic(
            $request->validated(),
            $request->file('photos', []) ?? [],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dikirim',
            'data'    => new HeavyEquipmentLogResource($log),
        ], 201);
    }
}
