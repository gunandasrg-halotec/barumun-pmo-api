<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeavyEquipmentLogPhoto extends Model
{
    use HasUuids;

    protected $fillable = [
        'heavy_equipment_log_id',
        'storage_path',
        'original_file_name',
        'mime_type',
        'photo_date',
    ];

    protected function casts(): array
    {
        return ['photo_date' => 'date'];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(HeavyEquipmentLog::class, 'heavy_equipment_log_id');
    }
}
