<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UploadedFile extends Model
{
    protected $fillable = [
        'user_id', 'folder', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'checksum',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->storage_path);
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
