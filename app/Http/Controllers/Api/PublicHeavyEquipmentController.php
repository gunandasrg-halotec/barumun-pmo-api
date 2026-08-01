<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\FuelStockReceiptRequest;
use App\Http\Requests\HeavyEquipmentLogRequest;
use App\Http\Resources\FuelStockReceiptResource;
use App\Http\Resources\HeavyEquipmentActivityTypeResource;
use App\Http\Resources\HeavyEquipmentCostItemResource;
use App\Http\Resources\HeavyEquipmentLogResource;
use App\Http\Resources\HeavyEquipmentResource;
use App\Models\FuelStockReceipt;
use App\Models\FuelStockReceiptPhoto;
use App\Models\HeavyEquipment;
use App\Models\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentCostItem;
use App\Services\HeavyEquipmentLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    #[OA\Post(
        tags: [HEAVY_EQUIPMENT_PUBLIC_TAG],
        path: "/v1/public/heavy-equipment/fuel-receipts",
        operationId: "PublicHeavyEquipmentController@storeFuelReceipts",
        summary: "Submit penerimaan stock BBM dari lapangan (bulk, multi jenis per hari)."
    )]
    #[Response2xx(response: "201", description: "Fuel receipts created")]
    #[ResponseDefault()]
    public function storeFuelReceipts(FuelStockReceiptRequest $request): JsonResponse
    {
        $validated   = $request->validated();
        $kebun       = $validated['kebun'];
        $receiptDate = $validated['receipt_date'];
        $ip          = $request->ip();
        $entries     = $request->decodedReceipts();
        $photos      = $request->file('photos', []) ?? [];

        $notes = $request->input('notes') ?: null;

        $created = collect($entries)->map(function (array $entry) use ($kebun, $receiptDate, $ip, $notes) {
            $total = FuelStockReceipt::computeTotal(
                (int) ($entry['qty_20l'] ?? 0),
                (int) ($entry['qty_30l'] ?? 0),
                (int) ($entry['qty_35l'] ?? 0),
                (int) ($entry['qty_40l'] ?? 0),
            );

            return FuelStockReceipt::create([
                'receipt_date' => $receiptDate,
                'kebun'        => $kebun,
                'fuel_type'    => $entry['fuel_type'],
                'qty_20l'      => (int) ($entry['qty_20l'] ?? 0),
                'qty_30l'      => (int) ($entry['qty_30l'] ?? 0),
                'qty_35l'      => (int) ($entry['qty_35l'] ?? 0),
                'qty_40l'      => (int) ($entry['qty_40l'] ?? 0),
                'total_liters' => $total,
                'notes'        => $notes,
                'submitted_ip' => $ip,
                'source'       => 'PUBLIC',
            ]);
        });

        // Upload foto dan lampirkan ke semua receipt dalam batch ini
        if (!empty($photos)) {
            $folder = BucketFolder . '/fuel-stock-receipts/' . $kebun . '/' . $receiptDate;
            foreach ($photos as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $storagePath = $folder . '/' . uniqid('', true) . '.jpg';
                Storage::disk('s3')->put($storagePath, fopen($file->getRealPath(), 'r'));

                foreach ($created as $receipt) {
                    FuelStockReceiptPhoto::create([
                        'fuel_stock_receipt_id' => $receipt->id,
                        'storage_path'          => $storagePath,
                        'original_file_name'    => $file->getClientOriginalName(),
                        'mime_type'             => $file->getMimeType() ?? 'image/jpeg',
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan BBM berhasil disimpan',
            'data'    => FuelStockReceiptResource::collection($created),
        ], 201);
    }
}
