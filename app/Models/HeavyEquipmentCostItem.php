<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HeavyEquipmentCostItem extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'default_amount', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ];
    }
}
