<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeavyEquipmentLogCost extends Model
{
    use HasUuids;

    protected $fillable = [
        'heavy_equipment_log_id',
        'heavy_equipment_cost_item_id',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(HeavyEquipmentLog::class, 'heavy_equipment_log_id');
    }

    public function costItem(): BelongsTo
    {
        return $this->belongsTo(HeavyEquipmentCostItem::class, 'heavy_equipment_cost_item_id');
    }
}
