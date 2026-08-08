<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Models\FuelStockReceipt;
use App\Models\FuelStockReceiptPhoto;
use App\Models\HeavyEquipmentLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class FuelStockController extends Controller
{
    #[OA\Get(
        tags: ['Fuel Stock'],
        path: "/v1/heavy-equipment/fuel-stock",
        operationId: "FuelStockController@index",
        summary: "Ledger penerimaan BBM + pemakaian + saldo kumulatif per jenis, per kebun."
    )]
    #[Response2xx(description: "Fuel stock ledger")]
    #[ResponseDefault()]
    public function index(Request $request): JsonResponse
    {
        $kebun    = $request->query('kebun');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $solar    = $this->buildLedger('solar',    $kebun, $dateFrom, $dateTo);
        $dexLite  = $this->buildLedger('dex_lite', $kebun, $dateFrom, $dateTo);
        $pertadex = $this->buildLedger('pertadex', $kebun, $dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'message' => 'Fuel stock fetched successfully',
            'data'    => [
                'solar'    => $solar,
                'dex_lite' => $dexLite,
                'pertadex' => $pertadex,
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
        // ── Penerimaan ──────────────────────────────────────────────────────────
        $receiptQuery = FuelStockReceipt::with('photos')
            ->where('fuel_type', $fuelType)
            ->orderBy('receipt_date');

        if ($kebun)    $receiptQuery->where('kebun', $kebun);
        if ($dateFrom) $receiptQuery->whereDate('receipt_date', '>=', $dateFrom);
        if ($dateTo)   $receiptQuery->whereDate('receipt_date', '<=', $dateTo);

        $receipts = $receiptQuery->get();

        // ── Pemakaian dari laporan alat berat ────────────────────────────────
        // Solar    → field fuel_liters
        // DexLite  → field fuel_liters_dex_lite
        // Pertadex → field fuel_liters_pertadex
        $usageByDate = collect();
        $usageColumn = match ($fuelType) {
            'solar'    => 'fuel_liters',
            'dex_lite' => 'fuel_liters_dex_lite',
            'pertadex' => 'fuel_liters_pertadex',
        };

        $usageQuery = HeavyEquipmentLog::select(
                'log_date',
                DB::raw("SUM({$usageColumn}) as total_liters")
            )
            ->whereNotNull($usageColumn)
            ->where($usageColumn, '>', 0);

        if ($kebun)    $usageQuery->where('kebun', $kebun);
        if ($dateFrom) $usageQuery->whereDate('log_date', '>=', $dateFrom);
        if ($dateTo)   $usageQuery->whereDate('log_date', '<=', $dateTo);

        $usageByDate = $usageQuery->groupBy('log_date')->orderBy('log_date')->get();

        // ── Gabungkan & urutkan kronologis ───────────────────────────────────
        $receiptEvents = $receipts->map(fn ($r) => [
            'date'       => $r->receipt_date?->toDateString(),
            'entry_type' => 'receipt',
            'model'      => $r,
            'liters'     => (float) $r->total_liters,
        ]);

        $usageEvents = $usageByDate->map(fn ($u) => [
            'date'       => $u->log_date instanceof \Carbon\Carbon
                                ? $u->log_date->toDateString()
                                : (string) $u->log_date,
            'entry_type' => 'usage',
            'model'      => null,
            'liters'     => (float) $u->total_liters,
        ]);

        $allEvents = $receiptEvents->concat($usageEvents)
            ->sortBy('date')
            ->values();

        // ── Running saldo ────────────────────────────────────────────────────
        $saldo         = 0.0;
        $totalReceived = 0.0;
        $totalUsed     = 0.0;

        $entries = $allEvents->map(function (array $event) use (&$saldo, &$totalReceived, &$totalUsed) {
            if ($event['entry_type'] === 'receipt') {
                $r      = $event['model'];
                $saldo += $event['liters'];
                $totalReceived += $event['liters'];

                $photos = $r->photos->map(fn ($p) => [
                    'id'           => $p->id,
                    'download_url' => str_replace('http://', 'https://', route('fuel-stock.photo.download', ['photo' => $p->id])),
                ])->values();

                return [
                    'id'           => $r->id,
                    'entry_type'   => 'receipt',
                    'receipt_date' => $event['date'],
                    'kebun'        => $r->kebun,
                    'qty_20l'      => $r->qty_20l,
                    'qty_30l'      => $r->qty_30l,
                    'qty_35l'      => $r->qty_35l ?? 0,
                    'qty_40l'      => $r->qty_40l,
                    'extra_liters' => (float) ($r->extra_liters ?? 0),
                    'total_liters' => $event['liters'],
                    'saldo'        => $saldo,
                    'notes'        => $r->notes,
                    'photos'       => $photos,
                ];
            } else {
                $saldo -= $event['liters'];
                $totalUsed += $event['liters'];

                return [
                    'id'           => 'usage-' . $event['date'],
                    'entry_type'   => 'usage',
                    'receipt_date' => $event['date'],
                    'kebun'        => null,
                    'qty_20l'      => 0,
                    'qty_30l'      => 0,
                    'qty_35l'      => 0,
                    'qty_40l'      => 0,
                    'total_liters' => -$event['liters'],
                    'saldo'        => $saldo,
                    'notes'        => null,
                    'photos'       => [],
                ];
            }
        });

        return [
            'total_received' => $totalReceived,
            'total_used'     => $totalUsed,
            'saldo'          => $saldo,
            'entries'        => $entries->values(),
        ];
    }
}
