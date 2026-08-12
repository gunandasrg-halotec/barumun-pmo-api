<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeavyEquipment extends Model
{
    use HasUuids;

    // "equipment" dianggap uncountable oleh inflector Laravel → tabel default
    // jadi "heavy_equipment". Set eksplisit agar cocok dengan migrasi.
    protected $table = 'heavy_equipments';

    protected $fillable = ['code', 'type', 'brand', 'is_active', 'is_vendor_owned'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_vendor_owned' => 'boolean'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HeavyEquipmentLog::class);
    }
}
