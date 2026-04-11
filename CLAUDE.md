# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

```
osint-framework/
├── osint-web/      Laravel 13 app (UI, auth, storage, orchestrator)
└── osint-engine/   Python FastAPI service (dynamic transform discovery + execution)
```

Laravel talks to the engine over HTTP with a shared secret; the engine is
NOT exposed publicly. Everything the browser sees goes through Laravel.

## Big picture

- **Auth**: `/setup` runs once when the `users` table is empty (middleware
  `EnsureSetupComplete` enforces this). Subsequent operators are created only
  through crypto-strong invite links (`/invite/{token}`) generated from
  `/users`. There is no `/register`.
- **Flat permissions**: every authenticated operator sees every project.
- **Entity types** are defined in `config/osint.php` and drive node
  color/shape/icon in Cytoscape. The Python engine is free to emit any `type`
  string; unknown types fall back to the `unknown` style.
- **Graphs are generic**: an investigation and a template are both rows in
  `graphs` distinguished by `type`. Template nodes use special entity types
  `template:input` (root slot) and `template:transform` (carries
  `data.transform_name`).
- **Transform execution is asynchronous via Laravel queue**:
  1. `GraphApiController@runTransform` creates an `investigation_jobs` row and
     dispatches `RunTransformJob`. Response is `{job_id, status: queued}`.
  2. `php artisan queue:work` picks the job up, calls `EngineClient` → FastAPI
     → `runner.run_transform`, persists new `graph_nodes`/`graph_edges`,
     appends them to `created_nodes`/`created_edges` on the job row, bumps
     `progress_done`.
  3. Frontend polls `GET /api/jobs/{id}?since_nodes=N&since_edges=M` every
     ~700ms in `public/js/graph.js` (`pollJob`), streaming new nodes into
     Cytoscape and updating the progress bar in the "Active jobs" sidebar.
  Each engine call is additionally audited in `transformation_runs` joined
  to the job via `job_id`.
- **Template execution** uses the same pipeline via `RunTemplateJob` →
  `TemplateRunner`. The runner topo-sorts the template, binds `template:input`
  slots to the user's selection, fans out each transform step over parent
  results, and writes into the same `investigation_jobs` row incrementally so
  the UI sees nodes appear as they're computed.
- **API key vault**: `api_keys` stores `Crypt::encryptString` ciphertexts. Keys
  are decrypted server-side only when passed into an engine run, via
  `ApiKeyResolver::resolveMany([...])`.

## Common commands

### Engine (from `osint-engine/`)

```bash
python -m venv .venv && .venv\Scripts\activate
pip install -e .[dev,extras]
python -m osint_engine.main        # uvicorn on 127.0.0.1:8077
pytest                             # unit + FastAPI TestClient tests
```

Add a third-party library to `pyproject.toml` → `[project.optional-dependencies] extras`, then `pip install -e .[extras]`.

### Laravel (from `osint-web/`)

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
php artisan osint:demo             # seed sample project + templates
php artisan test                   # Pest (in-memory sqlite via phpunit.xml)
php artisan test --filter=Invite   # single suite
./vendor/bin/pint                  # format
```

`composer run dev` still works (serves app + queue listener + Vite), but Vite
is not actually required — the UI loads assets straight from `public/css` and
`public/js`. No Node/React/Vue anywhere by design.

## Architectural conventions

- **No Node bundler**: Cytoscape.js and CodeMirror 6 load from CDN (`cdn.jsdelivr.net`, `esm.sh`). Blade + vanilla JS only.
- **Tests never hit MySQL or the real engine**: `phpunit.xml` forces SQLite `:memory:`, and every test that involves engine calls uses `Http::fake()`.
- **Graph scale**: Cytoscape is configured with `textureOnViewport`, `hideEdgesOnViewport`, `pixelRatio: 1`. Positions are persisted via a debounced `PATCH /api/graphs/{id}/nodes/{cyId}` on `dragfree`. The minimap is a small custom canvas — cheap to redraw even for tens of thousands of nodes — with a draggable viewport rectangle.
- **Theme**: CSS variables switched via `data-theme="dark|light"` on `<html>`, persisted in `localStorage`. All animations respect `prefers-reduced-motion`.
- **CSRF**: every JSON call goes through `window.csrfFetch` which reads the `<meta name="csrf-token">` tag Laravel writes in the layout.
- **Invite tokens**: 32 random bytes → base64url (43 chars), verified with `hash_equals`. One-shot, TTL via `OSINT_INVITE_TTL_HOURS`.

## Where things live

- Python transforms: `osint-engine/transforms/*.py`
- Engine HTTP surface: `osint-engine/osint_engine/main.py`
- Engine SDK (decorator + Node): `osint-engine/osint_engine/sdk.py`
- Entity type registry: `osint-web/config/osint.php`
- Graph JS (Cytoscape + minimap + context menu + template editor): `osint-web/public/js/graph.js`
- CodeMirror editor wiring: `osint-web/public/js/editor.js`
- Template execution: `osint-web/app/Services/TemplateRunner.php`
- Engine client: `osint-web/app/Services/EngineClient.php`
- Hacker theme CSS: `osint-web/public/css/app.css`

## Do not

- Add Node/React/Vue — the whole UI is intentionally Blade + vanilla JS + CDN libraries.
- Add SMTP/email — invites are copy-paste only by design.
- Expose the Python engine to the public network — it has no per-user auth.
- Use `$table->enum(...)` in migrations — use string + application-side constants to keep Postgres compatibility.
