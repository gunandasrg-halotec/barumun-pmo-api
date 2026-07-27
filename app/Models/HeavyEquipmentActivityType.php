<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HeavyEquipmentActivityType extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'unit',
        'allow_date_range',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_date_range' => 'boolean',
            'is_active'        => 'boolean',
        ];
    }
}
