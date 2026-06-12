<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbdNode extends Model
{
    use HasUuids;

    protected $table = 'wbd_nodes';

    protected $fillable = [
        'wbd_version_id',
        'parent_node_id',
        'node_type',
        'code',
        'name',
        'description',
        'unit',
        'volume',
        'rate',
        'planned_cost',
        'component_percent',
        'total_percent',
        'start_date',
        'duration_days',
        'end_date',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'volume' => 'decimal:4',
            'rate' => 'decimal:2',
            'planned_cost' => 'decimal:2',
            'component_percent' => 'decimal:4',
            'total_percent' => 'decimal:4',
        ];
    }

    public function wbdVersion(): BelongsTo
    {
        return $this->belongsTo(WbdVersion::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WbdNode::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WbdNode::class, 'parent_node_id')->orderBy('sort_order');
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class);
    }

    public function isGroup(): bool
    {
        return $this->node_type === 'GROUP';
    }

    public function isItem(): bool
    {
        return $this->node_type === 'ITEM';
    }

    public function calculateEndDate(): ?string
    {
        if ($this->start_date && $this->duration_days) {
            return $this->start_date->addDays($this->duration_days - 1)->toDateString();
        }
        return null;
    }

    public function calculatePlannedCost(): float
    {
        if ($this->isItem() && $this->volume !== null && $this->rate !== null) {
            return (float) $this->volume * (float) $this->rate;
        }
        return 0;
    }
}
