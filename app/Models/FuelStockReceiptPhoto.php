<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelStockReceiptPhoto extends Model
{
    use HasUuids;

    protected $fillable = [
        'fuel_stock_receipt_id',
        'storage_path',
        'original_file_name',
        'mime_type',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FuelStockReceipt::class, 'fuel_stock_receipt_id');
    }
}
