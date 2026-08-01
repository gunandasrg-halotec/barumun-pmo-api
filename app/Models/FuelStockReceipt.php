<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelStockReceipt extends Model
{
    use HasUuids;

    protected $fillable = [
        'receipt_date',
        'kebun',
        'fuel_type',
        'qty_20l',
        'qty_30l',
        'qty_35l',
        'qty_40l',
        'extra_liters',
        'total_liters',
        'notes',
        'submitted_ip',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'total_liters' => 'float',
            'qty_20l'      => 'integer',
            'qty_30l'      => 'integer',
            'qty_35l'      => 'integer',
            'qty_40l'      => 'integer',
            'extra_liters' => 'float',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(FuelStockReceiptPhoto::class, 'fuel_stock_receipt_id');
    }

    public static function computeTotal(int $qty20l, int $qty30l, int $qty35l, int $qty40l, float $extraLiters = 0): float
    {
        return ($qty20l * 20) + ($qty30l * 30) + ($qty35l * 35) + ($qty40l * 40) + $extraLiters;
    }
}
