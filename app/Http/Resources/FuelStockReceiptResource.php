<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class FuelStockReceiptResource extends ModelResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'kebun'        => $this->kebun,
            'fuel_type'    => $this->fuel_type,
            'qty_20l'      => $this->qty_20l,
            'qty_30l'      => $this->qty_30l,
            'qty_40l'      => $this->qty_40l,
            'total_liters' => (float) $this->total_liters,
            'created_at'   => $this->created_at,
        ];
    }
}
