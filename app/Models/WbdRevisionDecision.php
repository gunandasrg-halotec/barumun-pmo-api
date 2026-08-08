<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WbdRevisionDecision extends Model
{
    use HasUuids;

    protected $table = 'wbd_revision_decisions';

    protected $fillable = [
        'wbd_version_id',
        'node_code',
        'change_type',
        'decision',
        'reason',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function wbdVersion(): BelongsTo
    {
        return $this->belongsTo(WbdVersion::class);
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
