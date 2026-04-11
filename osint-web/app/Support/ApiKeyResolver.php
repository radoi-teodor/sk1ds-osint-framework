<?php

namespace App\Support;

use App\Models\ApiKey;

class ApiKeyResolver
{
    /**
     * Return a map of { name => decrypted value } for the given key names.
     * Missing keys are simply absent from the result.
     *
     * @param  string[]  $names
     * @return array<string, string>
     */
    public static function resolveMany(array $names): array
    {
        if ($names === []) {
            return [];
        }
        $rows = ApiKey::whereIn('name', $names)->get();
        $out = [];
        foreach ($rows as $row) {
            try {
                $out[$row->name] = $row->getValue();
            } catch (\Throwable) {
                // ignore — transform will see it as "missing"
            }
        }
        return $out;
    }
}
