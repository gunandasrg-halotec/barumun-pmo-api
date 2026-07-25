<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Services\HeavyEquipmentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class HeavyEquipmentAnalyticsController extends Controller
{
    public function __construct(private HeavyEquipmentAnalyticsService $analytics)
    {
    }

    #[OA\Get(
        tags: [HEAVY_EQUIPMENT_ANALYTICS_TAG],
        path: "/v1/heavy-equipment/analytics",
        operationId: "HeavyEquipmentAnalyticsController@index",
        summary: "Analitik penggunaan alat berat: KPI, deret harian, per jenis pekerjaan, per unit, per item biaya.",
        parameters: [
            new OA\Parameter(in: "query", name: "date_from", schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "date_to", schema: new Schema(type: "string", format: "date")),
            new OA\Parameter(in: "query", name: "equipment_id", schema: new Schema(type: "string", format: "uuid")),
            new OA\Parameter(in: "query", name: "kebun", schema: new Schema(type: "string")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Analytics summary")]
    #[ResponseDefault()]
    public function index(Request $request): JsonResponse
    {
        $result = $this->analytics->summary([
            'date_from'    => $request->get('date_from'),
            'date_to'      => $request->get('date_to'),
            'equipment_id' => $request->get('equipment_id'),
            'kebun'        => $request->get('kebun'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Analytics fetched successfully',
            'data'    => $result,
        ]);
    }
}
