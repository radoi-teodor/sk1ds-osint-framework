# OSINT Framework

A Maltego-style OSINT investigation platform.

- **osint-web/** — Laravel 13 application: UI, auth (with setup mode + invite
  links), projects, graphs, templates, transformation editor, API key vault.
- **osint-engine/** — Python FastAPI service: dynamically discovers
  transformation modules from `transforms/*.py` and runs them over HTTP.
  Should NEVER be exposed publicly — Laravel is the only client.

The platform name shown in the UI is taken from Laravel's `config('app.name')`
(environment variable `APP_NAME`). The eye icon next to it follows your cursor.

---

## Architecture at a glance

```
Browser (Blade + vanilla JS + Cytoscape)
        │  HTTPS + CSRF
        ▼
Laravel 13  ─────  MySQL / Postgres   (users, projects, graphs, api_keys, runs)
        │
        │  HTTP + shared secret (127.0.0.1 only)
        ▼
Python FastAPI engine  ──  transforms/*.py  (hot-reloadable)
```

## Prerequisites

- PHP 8.3+ (tested on Herd)
- Composer
- MySQL (XAMPP is fine) — migrations are also compatible with PostgreSQL
- Python 3.11+ (tested with 3.14)

## First-time setup

### 1. Python engine

```bash
cd osint-engine
python -m venv .venv
# Windows
.venv\Scripts\activate
# or macOS/Linux
source .venv/bin/activate

pip install -e .[dev,extras]
copy .env.example .env            # optional — defaults are fine for dev
```

Run it:

```bash
python -m osint_engine.main
# -> listens on http://127.0.0.1:8077
```

### 2. Laravel app

```bash
cd osint-web
composer install
copy .env.example .env
php artisan key:generate
```

Create the MySQL database (via XAMPP/phpMyAdmin or CLI):

```sql
CREATE DATABASE osint_framework CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
php artisan migrate
php artisan serve
```

Open `http://127.0.0.1:8000`. Because there are no users, you'll be redirected
to `/setup` — create the first admin operator there.

### 3. (Optional) seed sample data

After logging in for the first time:

```bash
cd osint-web
php artisan osint:demo
```

This creates:

- **DEMO-RECON** project with an `example.com` investigation graph pre-seeded.
- Three templates: *Domain recon*, *Email recon*, *Offline demo*.

Open the investigation at `/projects` → DEMO-RECON → click the example.com
graph. Select the `example.com` node and hit one of the "Run template" buttons
in the right sidebar, or right-click the node for the individual transform
menu.

## Development loop

The platform runs transformations **asynchronously through Laravel's queue**,
so dev needs three processes running: Laravel HTTP, the queue worker, and
the Python engine. They're all wired into `composer run dev`:

```bash
cd osint-web
composer run dev        # Windows
composer run dev:unix   # macOS / Linux (different venv path)
```

This uses `npx concurrently` (already a transitive dependency of the Laravel
scaffold) to spawn:

| Name   | Command                                          |
|--------|--------------------------------------------------|
| web    | `php artisan serve` (127.0.0.1:8000)             |
| queue  | `php artisan queue:work --tries=1 --verbose`     |
| engine | `..\osint-engine\.venv\Scripts\python.exe -m osint_engine.main` (127.0.0.1:8077) |

`Ctrl+C` stops all three (`-k` flag). You still need to have run the
one-time setup (`composer install`, `pip install -e .[dev,extras]`,
`php artisan migrate`) beforehand — see "First-time setup" above.

Shared secret must match in both `.env` files (`ENGINE_SHARED_SECRET` on the
engine side, `OSINT_ENGINE_SECRET` on the Laravel side).

### How investigations actually run

1. Browser calls `POST /api/graphs/{id}/run-transform` (or `run-template`) →
   Laravel creates an `investigation_jobs` row and dispatches a queued job
   (`RunTransformJob` / `RunTemplateJob`). Response is instant:
   `{ok, job_id, status: "queued"}`.
2. `php artisan queue:work` picks the job up, calls the Python engine for each
   source node, persists result nodes + edges, appends them to
   `investigation_jobs.created_nodes`/`created_edges` and bumps
   `progress_done` as it goes.
3. The browser polls `GET /api/jobs/{id}?since_nodes=N&since_edges=M` every
   ~700ms, adding only the newly-appended nodes/edges to Cytoscape. The
   right-hand sidebar shows an "Active jobs" panel with a live progress bar.
4. When `status` is `completed` / `failed` / `cancelled`, polling stops.

Queue connection is `database` by default (see `.env.example`). Switch to
Redis/Horizon later without touching any code. Scale horizontally by running
more `queue:work` processes — each investigation job is self-contained.

## Running tests

```bash
# engine
cd osint-engine && pytest

# laravel
cd osint-web && php artisan test
```

Laravel tests use an in-memory SQLite database (see `phpunit.xml`) so they
never touch your MySQL. The tests covering transform execution fake the
engine via Laravel's `Http::fake()`.

## Operator workflow

1. First user creates the admin via `/setup`.
2. Admin issues invite links from `/users`. Links are 43-char base64url tokens
   derived from `random_bytes(32)`. Verified timing-safely with `hash_equals`.
   One-shot, expire after `OSINT_INVITE_TTL_HOURS` (default 72).
3. Every authenticated operator sees every project (flat permissions —
   platform-wide collaboration).

## Writing your own transforms

Drop a file into `osint-engine/transforms/`:

```python
from osint_engine.sdk import transform, Node

@transform(
    name="my.probe",
    display_name="My probe",
    description="Does the thing",
    category="custom",
    input_types=["domain"],
    output_types=["note"],
    required_api_keys=["MY_API_KEY"],   # optional
    timeout=10,
)
def run(node, api_keys):
    return [Node(type="note", value=f"hi {node.value}")]
```

Hit "Reload engine" in the UI (or POST `/api/transformations/reload`). Your
transform appears instantly in the context menu of every compatible node.

You can also write transforms directly from the UI: `/transformations/new`.

### API keys

Store a key in `/api-keys` using the exact `name` your transform requires
(e.g. `C99_API_KEY`). It is encrypted with Laravel's `Crypt::encryptString`
(AES-256-CBC, derived from `APP_KEY`) and only decrypted server-side when
passed to the engine.

### Third-party Python libraries

Add them to `osint-engine/pyproject.toml` under
`[project.optional-dependencies] extras`, then
`pip install -e .[extras]`. `requests`, `dnspython`, `python-whois` and
`beautifulsoup4` are pre-listed.

## Graph UI

- Right-click a node → context menu with transforms whose `input_types`
  match that node's type.
- Drag to pan, scroll to zoom, drag nodes to reposition (persisted
  automatically).
- Minimap (bottom-right) shows the whole graph; click or drag on it to
  navigate the viewport on large graphs.
- Cytoscape is configured with `textureOnViewport` and edge hiding during
  viewport changes — tested to remain smooth with tens of thousands of nodes.

## Templates

A template is just another graph (`type = template`) whose nodes are either
`template:input` (root slots, bound to the investigation nodes you select) or
`template:transform` (carry a `data.transform_name`). Edges are data flow. Use
**shift-click** on two nodes inside a template editor to connect them. The
runner topologically sorts the template and runs each step against all
resulting investigation nodes produced by its parents — hierarchical fan-out
included.
