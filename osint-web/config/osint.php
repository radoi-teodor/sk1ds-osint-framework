<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Python engine connection
    |--------------------------------------------------------------------------
    */

    'engine' => [
        'url' => env('OSINT_ENGINE_URL', 'http://127.0.0.1:8077'),
        'secret' => env('OSINT_ENGINE_SECRET', 'dev-secret-change-me'),
        'timeout' => (int) env('OSINT_ENGINE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invites
    |--------------------------------------------------------------------------
    */

    'invite_ttl_hours' => (int) env('OSINT_INVITE_TTL_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Entity types
    |--------------------------------------------------------------------------
    |
    | These drive the graph UI: node color, shape, and icon. The Python engine
    | is free to emit any type string — unknown types fall back to the
    | 'unknown' style below so nothing breaks when you add a new transform.
    |
    */

    'entity_types' => [
        'domain' => ['label' => 'Domain', 'color' => '#00ff9c', 'shape' => 'round-rectangle', 'icon' => '🌐'],
        'ipv4' => ['label' => 'IPv4', 'color' => '#66ffcc', 'shape' => 'rectangle', 'icon' => '▦'],
        'ipv6' => ['label' => 'IPv6', 'color' => '#66ccff', 'shape' => 'rectangle', 'icon' => '▩'],
        'url' => ['label' => 'URL', 'color' => '#ffb000', 'shape' => 'round-rectangle', 'icon' => '🔗'],
        'email' => ['label' => 'Email', 'color' => '#ff6ec7', 'shape' => 'round-rectangle', 'icon' => '✉'],
        'phone' => ['label' => 'Phone', 'color' => '#ffd93d', 'shape' => 'round-rectangle', 'icon' => '☎'],
        'person' => ['label' => 'Person', 'color' => '#ff8c42', 'shape' => 'ellipse', 'icon' => '👤'],
        'organization' => ['label' => 'Organization', 'color' => '#b4a7ff', 'shape' => 'round-hexagon', 'icon' => '🏢'],
        'location' => ['label' => 'Location', 'color' => '#ffe15c', 'shape' => 'round-diamond', 'icon' => '📍'],
        'hash' => ['label' => 'Hash', 'color' => '#c3f584', 'shape' => 'hexagon', 'icon' => '#'],
        'file' => ['label' => 'File', 'color' => '#a0d8ef', 'shape' => 'round-rectangle', 'icon' => '📄'],
        'title' => ['label' => 'Title', 'color' => '#e0e0e0', 'shape' => 'round-rectangle', 'icon' => '📰'],
        'note' => ['label' => 'Note', 'color' => '#cccccc', 'shape' => 'round-rectangle', 'icon' => '📝'],
        'whois_record' => ['label' => 'WHOIS', 'color' => '#7fffd4', 'shape' => 'round-tag', 'icon' => '📋'],
        'asn' => ['label' => 'ASN', 'color' => '#00ccff', 'shape' => 'round-hexagon', 'icon' => 'AS'],
        'port' => ['label' => 'Port', 'color' => '#ff4444', 'shape' => 'round-rectangle', 'icon' => '🔌'],
        'certificate' => ['label' => 'Certificate', 'color' => '#f4a261', 'shape' => 'round-rectangle', 'icon' => '🔏'],
        'unknown' => ['label' => 'Unknown', 'color' => '#888888', 'shape' => 'round-rectangle', 'icon' => '?'],
    ],

];
