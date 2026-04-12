<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_TRANSFORM = 'transform';
    public const KIND_TEMPLATE = 'template';

    protected $fillable = [
        'graph_id', 'user_id', 'kind', 'transform_name', 'template_id',
        'slave_id', 'generator_name', 'generator_file_id', 'generator_text_input',
        'generator_output', 'source_cy_ids', 'status', 'progress_done', 'progress_total',
        'created_nodes', 'created_edges', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'source_cy_ids' => 'array',
            'created_nodes' => 'array',
            'created_edges' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function graph(): BelongsTo
    {
        return $this->belongsTo(Graph::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Graph::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED,
        ], true);
    }

    /** Append nodes/edges to the accumulated output atomically-ish. */
    public function appendOutput(array $nodes, array $edges): void
    {
        $this->refresh();
        $this->created_nodes = array_merge($this->created_nodes ?? [], $nodes);
        $this->created_edges = array_merge($this->created_edges ?? [], $edges);
        $this->save();
    }
}
