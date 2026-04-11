<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationRun extends Model
{
    protected $fillable = [
        'job_id', 'user_id', 'graph_id', 'source_cy_id',
        'transform_name', 'input_type', 'input_value',
        'output', 'error', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'output' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(Graph::class);
    }
}
