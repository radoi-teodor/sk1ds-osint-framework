<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Graph extends Model
{
    public const TYPE_INVESTIGATION = 'investigation';
    public const TYPE_TEMPLATE = 'template';

    protected $fillable = ['project_id', 'title', 'type', 'description', 'meta', 'created_by'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(GraphNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(GraphEdge::class);
    }

    public function isTemplate(): bool
    {
        return $this->type === self::TYPE_TEMPLATE;
    }
}
