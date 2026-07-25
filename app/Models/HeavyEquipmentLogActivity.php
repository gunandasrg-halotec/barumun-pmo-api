<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeavyEquipmentLogActivity extends Model
{
    use HasUuids;

    protected $fillable = [
        'heavy_equipment_log_id',
        'activity_type',
        'start_time',
        'end_time',
        'volume',
        'unit',
    ];

    protected function casts(): array
    {
        return ['volume' => 'decimal:2'];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(HeavyEquipmentLog::class, 'heavy_equipment_log_id');
    }
}
