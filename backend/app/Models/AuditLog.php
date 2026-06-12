<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action_type',
        'action_by',
        'action_at',
        'old_value_json',
        'new_value_json',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'action_at' => 'datetime',
            'old_value_json' => 'array',
            'new_value_json' => 'array',
        ];
    }

    public function actionByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
