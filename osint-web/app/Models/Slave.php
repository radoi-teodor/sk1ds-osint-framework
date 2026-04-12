<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Slave extends Model
{
    protected $fillable = [
        'name', 'type', 'host', 'port', 'username',
        'auth_method', 'fingerprint', 'last_tested_at', 'created_by',
    ];

    protected $hidden = ['encrypted_credential'];

    protected function casts(): array
    {
        return [
            'fingerprint' => 'array',
            'last_tested_at' => 'datetime',
            'port' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEmbedded(): bool
    {
        return $this->type === 'embedded';
    }

    public function setCredential(string $plain): void
    {
        $this->encrypted_credential = Crypt::encryptString($plain);
    }

    public function getCredential(): ?string
    {
        if ($this->isEmbedded() || ! $this->encrypted_credential) {
            return null;
        }
        return Crypt::decryptString($this->encrypted_credential);
    }

    public function maskedPreview(): string
    {
        if ($this->isEmbedded()) {
            return '(local)';
        }
        try {
            $v = $this->getCredential();
        } catch (\Throwable) {
            return '********';
        }
        if (! $v) {
            return '(none)';
        }
        if (str_contains($v, "BEGIN") && str_contains($v, "KEY")) {
            return 'SSH key (' . strlen($v) . ' chars)';
        }
        $len = strlen($v);
        return $len <= 4
            ? str_repeat('*', $len)
            : str_repeat('*', $len - 4) . substr($v, -4);
    }

    public function toEnginePayload(): array
    {
        return [
            'type' => $this->type,
            'host' => $this->host,
            'port' => $this->port ?? 22,
            'username' => $this->username,
            'auth_method' => $this->auth_method,
            'credential' => $this->getCredential(),
        ];
    }

    public function flagEmoji(): string
    {
        return $this->fingerprint['flag'] ?? '';
    }

    public function statusLine(): string
    {
        $fp = $this->fingerprint;
        if (! $fp) {
            return 'not probed';
        }
        $parts = array_filter([
            $fp['flag'] ?? '',
            $fp['country'] ?? '',
            $fp['isp'] ?? '',
            $fp['os'] ?? '',
        ]);
        return implode(' · ', $parts) ?: 'probed';
    }
}
