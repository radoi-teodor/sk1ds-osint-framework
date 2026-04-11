<?php

namespace App\Support;

class EntityTypes
{
    public static function all(): array
    {
        return (array) config('osint.entity_types', []);
    }

    public static function get(string $type): array
    {
        $all = self::all();
        return $all[$type] ?? $all['unknown'] ?? [
            'label' => 'Unknown',
            'color' => '#888888',
            'shape' => 'round-rectangle',
            'icon' => '?',
        ];
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }
}
