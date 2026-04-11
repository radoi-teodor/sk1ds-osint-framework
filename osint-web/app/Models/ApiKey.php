<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    protected $fillable = ['name', 'label', 'ciphertext', 'created_by'];

    protected $hidden = ['ciphertext'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setValue(string $plain): void
    {
        $this->ciphertext = Crypt::encryptString($plain);
    }

    public function getValue(): string
    {
        return Crypt::decryptString($this->ciphertext);
    }

    public function maskedPreview(): string
    {
        try {
            $v = $this->getValue();
        } catch (\Throwable) {
            return '********';
        }
        $len = strlen($v);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', max(0, $len - 4)) . substr($v, -4);
    }
}
