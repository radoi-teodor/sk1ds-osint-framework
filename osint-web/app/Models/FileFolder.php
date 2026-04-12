<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileFolder extends Model
{
    protected $fillable = ['path'];

    public function name(): string
    {
        return basename($this->path) ?: '/';
    }

    public function parentPath(): string
    {
        $parent = dirname($this->path);
        return $parent === '.' ? '/' : $parent;
    }
}
