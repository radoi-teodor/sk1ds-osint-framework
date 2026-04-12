<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SDK Docs') · {{ config('app.name') }} Docs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <style>
        .docs-wrap { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .docs-sidebar {
            background: var(--bg-elev); border-right: 1px solid var(--border);
            padding: 20px 16px; overflow-y: auto; position: sticky; top: 0; height: 100vh;
        }
        .docs-sidebar .brand { font-size: 16px; letter-spacing: 2px; margin-bottom: 6px; }
        .docs-sidebar .subtitle { color: var(--text-dim); font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 18px; }
        .docs-nav { display: flex; flex-direction: column; gap: 2px; }
        .docs-nav a {
            padding: 7px 12px; font-size: 12px; color: var(--text-dim);
            border: 1px solid transparent; transition: all 0.12s; display: flex; align-items: center; gap: 8px;
        }
        .docs-nav a:hover { color: var(--accent); background: var(--accent-soft); border-color: var(--border); text-decoration: none; }
        .docs-nav a.active { color: var(--accent); border-color: var(--accent); background: var(--accent-soft); }
        .docs-nav .nav-icon { width: 18px; text-align: center; font-size: 13px; }

        .docs-main { padding: 40px 48px; max-width: 900px; }
        .docs-main h1 { color: var(--accent); margin-bottom: 8px; }
        .docs-main h2 { color: var(--accent); margin-top: 32px; font-size: 18px; }
        .docs-main h3 { color: var(--text-strong); margin-top: 24px; font-size: 15px; }
        .docs-main p { line-height: 1.7; margin: 10px 0; }
        .docs-main code:not(pre code) {
            background: var(--bg-elev-2); border: 1px solid var(--border);
            padding: 1px 6px; font-size: 13px; color: var(--accent);
        }
        .docs-main pre {
            background: var(--bg-elev-2); border: 1px solid var(--border);
            padding: 16px; overflow-x: auto; font-size: 13px; line-height: 1.55; margin: 14px 0;
        }
        .docs-main table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 14px 0; }
        .docs-main th, .docs-main td { padding: 8px 12px; text-align: left; border-bottom: 1px dashed var(--border); }
        .docs-main th { color: var(--text-dim); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .docs-main .callout {
            border-left: 3px solid var(--accent); background: var(--accent-soft);
            padding: 12px 16px; margin: 16px 0; font-size: 13px;
        }
        .docs-main .callout.warn { border-color: var(--warn); background: rgba(255,176,0,0.08); }

        #docs-search { width: 100%; margin-bottom: 14px; font-size: 12px; padding: 8px 10px; }
        .search-results { display: none; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .search-results.open { display: flex; }
        .search-hit {
            padding: 8px 12px; background: var(--bg-elev-2); border: 1px solid var(--border);
            font-size: 12px; cursor: pointer;
        }
        .search-hit:hover { border-color: var(--accent); background: var(--accent-soft); }
        .search-hit .hit-title { color: var(--accent); font-weight: bold; }
        .search-hit .hit-snippet { color: var(--text-dim); font-size: 11px; margin-top: 3px; }
    </style>
</head>
<body>
<div class="docs-wrap">
    <aside class="docs-sidebar">
        <a href="/docs" class="brand" style="text-decoration:none">
            @include('partials.eye')
            <span class="brand-name">{{ config('app.name') }}</span>
        </a>
        <div class="subtitle">Transform SDK</div>

        <input type="search" id="docs-search" placeholder="Search docs... (Ctrl+K)" autocomplete="off">
        <div class="search-results" id="search-results"></div>

        <nav class="docs-nav">
            <a href="/docs" class="{{ request()->is('docs') && !request()->is('docs/*') ? 'active' : '' }}">
                <span class="nav-icon">⟫</span> Overview
            </a>
            @foreach($pages as $slug => $meta)
                <a href="/docs/{{ $slug }}" class="{{ ($currentPage ?? '') === $slug ? 'active' : '' }}">
                    <span class="nav-icon">{{ $meta['icon'] }}</span> {{ $meta['title'] }}
                </a>
            @endforeach
        </nav>

        <hr>
        <div style="font-size:11px;color:var(--text-dim);">
            <a href="/login" style="font-size:11px;">← Back to app</a>
        </div>
    </aside>
    <main class="docs-main">
        @yield('content')
    </main>
</div>
<script>
(function() {
    let index = null;
    const input = document.getElementById('docs-search');
    const results = document.getElementById('search-results');
    if (!input || !results) return;

    async function loadIndex() {
        if (index) return index;
        const r = await fetch('/docs/search.json');
        index = await r.json();
        return index;
    }

    input.addEventListener('input', async function() {
        const q = this.value.trim().toLowerCase();
        if (q.length < 2) { results.classList.remove('open'); return; }
        const idx = await loadIndex();
        const hits = idx.filter(p => p.title.toLowerCase().includes(q) || p.body.toLowerCase().includes(q));
        if (hits.length === 0) { results.innerHTML = '<div class="text-dim small" style="padding:6px;">No results</div>'; results.classList.add('open'); return; }
        results.innerHTML = hits.map(h => {
            const pos = h.body.toLowerCase().indexOf(q);
            const snippet = pos >= 0 ? '...' + h.body.substring(Math.max(0, pos - 40), pos + 80) + '...' : '';
            return `<a href="/docs/${h.slug}" class="search-hit"><div class="hit-title">${h.title}</div>${snippet ? `<div class="hit-snippet">${snippet}</div>` : ''}</a>`;
        }).join('');
        results.classList.add('open');
    });

    input.addEventListener('keydown', e => { if (e.key === 'Escape') { input.value = ''; results.classList.remove('open'); } });
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); input.focus(); input.select(); }
    });
})();
</script>
</body>
</html>
