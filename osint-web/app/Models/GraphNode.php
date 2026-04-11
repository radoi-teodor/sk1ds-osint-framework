<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraphNode extends Model
{
    protected $fillable = [
        'graph_id', 'cy_id', 'entity_type', 'value', 'label',
        'data', 'position_x', 'position_y', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'position_x' => 'float',
            'position_y' => 'float',
        ];
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(Graph::class);
    }
}
