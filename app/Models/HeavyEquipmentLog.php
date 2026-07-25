<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeavyEquipmentLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'heavy_equipment_id',
        'log_date',
        'kebun',
        'area',
        'operator',
        'kenek',
        'fuel_liters',
        'work_morning_start',
        'work_morning_end',
        'work_afternoon_start',
        'work_afternoon_end',
        'note',
        'source',
        'submitted_ip',
    ];

    protected function casts(): array
    {
        return [
            'log_date'    => 'date',
            'fuel_liters' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(HeavyEquipment::class, 'heavy_equipment_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(HeavyEquipmentLogActivity::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(HeavyEquipmentLogCost::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HeavyEquipmentLogPhoto::class);
    }

    /** Total biaya harian = jumlah semua item biaya yang diinput. */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->costs->sum('amount');
    }
}
