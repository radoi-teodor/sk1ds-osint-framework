<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaveSetupScript extends Model
{
    protected $fillable = ['name', 'description', 'script', 'is_default', 'created_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
