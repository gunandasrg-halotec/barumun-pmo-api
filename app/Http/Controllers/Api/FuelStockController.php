<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\FuelStockReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    private function buildLedger(string $fuelType, ?string $kebun, ?string $dateFrom, ?string $dateTo): array
    {
        $query = FuelStockReceipt::where('fuel_type', $fuelType)
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

            return [
                'id'           => $r->id,
                'receipt_date' => $r->receipt_date?->toDateString(),
                'kebun'        => $r->kebun,
                'qty_20l'      => $r->qty_20l,
                'qty_30l'      => $r->qty_30l,
                'qty_40l'      => $r->qty_40l,
                'total_liters' => (float) $r->total_liters,
                'saldo'        => (float) $saldo,
            ];
        });

        return [
            'total_received' => (float) $receipts->sum('total_liters'),
            'saldo'          => (float) $saldo,
            'entries'        => $entries->values(),
        ];
    }
}
