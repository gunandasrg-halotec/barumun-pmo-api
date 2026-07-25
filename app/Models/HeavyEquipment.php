<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeavyEquipment extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'type', 'brand', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HeavyEquipmentLog::class);
    }
}
