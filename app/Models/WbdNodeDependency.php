<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WbdNodeDependency extends Model
{
    use HasUuids;

    protected $table = 'wbd_node_dependencies';

    protected $fillable = [
        'predecessor_node_id',
        'successor_node_id',
        'dependency_type',
    ];

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(WbdNode::class, 'predecessor_node_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(WbdNode::class, 'successor_node_id');
    }
}
