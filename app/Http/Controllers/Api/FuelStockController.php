<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\FuelStockReceipt;
use App\Models\FuelStockReceiptPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class FuelStockController extends Controller
{
    #[OA\Get(
        tags: ['Fuel Stock'],
        path: "/v1/heavy-equipment/fuel-stock",
        operationId: "FuelStockController@index",
        summary: "Ledger penerimaan BBM + saldo kumulatif per jenis, per kebun."
    )]
    #[Response2xx(description: "Fuel stock ledger")]
    #[ResponseDefault()]
    public function index(Request $request): JsonResponse
    {
        $kebun    = $request->query('kebun');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $solar   = $this->buildLedger('solar',    $kebun, $dateFrom, $dateTo);
        $dexLite = $this->buildLedger('dex_lite', $kebun, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Fuel stock fetched successfully',
            'data'    => [
                'solar'    => $solar,
                'dex_lite' => $dexLite,
            ],
        ]);
    }

    public function downloadPhoto(FuelStockReceiptPhoto $photo): Response
    {
        $diskname = Str::startsWith($photo->storage_path, 'pmo') ? 's3' : 'local';
        abort_unless(Storage::disk($diskname)->exists($photo->storage_path), 404, 'File not found on disk.');

        return response(Storage::disk($diskname)->get($photo->storage_path), 200, [
            'Content-Type'        => $photo->mime_type,
            'Content-Disposition' => 'inline; filename="' . $photo->original_file_name . '"',
        ]);
    }

    private function buildLedger(string $fuelType, ?string $kebun, ?string $dateFrom, ?string $dateTo): array
    {
        $query = FuelStockReceipt::with('photos')
            ->where('fuel_type', $fuelType)
            ->orderBy('receipt_date');

        if ($kebun) {
            $query->where('kebun', $kebun);
        }
        if ($dateFrom) {
            $query->whereDate('receipt_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('receipt_date', '<=', $dateTo);
        }

        $receipts = $query->get();

        // Running saldo
        $saldo   = 0;
        $entries = $receipts->map(function (FuelStockReceipt $r) use (&$saldo) {
            $saldo += $r->total_liters;

            $photos = $r->photos->map(fn ($p) => [
                'id'           => $p->id,
                'download_url' => str_replace('http://', 'https://', route('fuel-stock.photo.download', ['photo' => $p->id])),
            ])->values();

            return [
                'id'           => $r->id,
                'receipt_date' => $r->receipt_date?->toDateString(),
                'kebun'        => $r->kebun,
                'qty_20l'      => $r->qty_20l,
                'qty_30l'      => $r->qty_30l,
                'qty_40l'      => $r->qty_40l,
                'total_liters' => (float) $r->total_liters,
                'saldo'        => (float) $saldo,
                'photos'       => $photos,
            ];
        });

        return [
            'total_received' => (float) $receipts->sum('total_liters'),
            'saldo'          => (float) $saldo,
            'entries'        => $entries->values(),
        ];
    }
}
