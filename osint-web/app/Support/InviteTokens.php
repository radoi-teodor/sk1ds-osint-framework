<?php

namespace App\Support;

class InviteTokens
{
    /**
     * Generate a cryptographically strong, URL-safe invite token.
     * 32 bytes of random_bytes() -> 43 chars base64url.
     */
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Timing-safe comparison. */
    public static function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
