<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function graphs(): HasMany
    {
        return $this->hasMany(Graph::class);
    }

    public function investigations(): HasMany
    {
        return $this->hasMany(Graph::class)->where('type', 'investigation');
    }
}
