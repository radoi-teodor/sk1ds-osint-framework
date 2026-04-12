# OSINT Framework

An open-source, Maltego-style investigation platform for open-source intelligence. Build visual graphs, run transformations on entities, automate recon workflows, and collaborate with your team — all from a single self-hosted web application.

## What is this?

Think of it as your own Maltego, but self-hosted, extensible, and with a hacker-aesthetic UI. You start with an entity (a domain, an email, an IP), right-click it, and run **transformations** — automated modules that discover related entities and connect them in a visual graph. Chain transformations into reusable **templates** for one-click automated investigations.

**Key capabilities:**

- **Investigation graphs** — Cytoscape-powered canvas with 20+ entity types (domain, IP, email, hash, port, person, breach, etc.), minimap navigation, multiple layout algorithms, and presentation mode
- **50+ built-in transformations** — DNS, WHOIS, crt.sh subdomains, HTTP probing, security header audit, Snusbase breach search, nmap port scanning, secrets scanning, and more
- **Template automation** — Build reusable investigation workflows as visual DAGs. 8 pre-built templates included (passive recon, active recon, leak investigation, web app audit)
- **Generators** — Feed wordlists and file data into transforms (directory bruteforce, subdomain enumeration)
- **SSH slaves** — Execute tools (nmap, feroxbuster, custom scripts) on remote servers via SSH or locally
- **API key vault** — Encrypted storage for third-party API credentials (Snusbase, Shodan, etc.)
- **File manager** — Upload and organize wordlists, CIDR lists, and other operational files
- **PDF reporting** — Flag investigation nodes and generate styled PDF reports
- **Team collaboration** — Invite-link based operator management, TOTP 2FA, flat permissions (everyone sees everything)
- **Extensible SDK** — Write custom transforms and generators in Python, deploy by dropping a `.py` file

## Quick start with Docker

The fastest way to get running. Requires [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/).

### Option A: Pre-built images from Docker Hub (no clone needed)

Download a single file and run — no build, no source code required:

```bash
# Download the compose file
curl -O https://raw.githubusercontent.com/radoi-teodor/osint-framework/main/docker-compose.hub.yml

# Start the platform
docker compose -f docker-compose.hub.yml up -d
```

Images pulled automatically from Docker Hub:
- [`edimemune/sk1ds-osint-framework-web`](https://hub.docker.com/r/edimemune/sk1ds-osint-framework-web)
- [`edimemune/sk1ds-osint-framework-engine`](https://hub.docker.com/r/edimemune/sk1ds-osint-framework-engine)

### Option B: Build from source

```bash
git clone https://github.com/radoi-teodor/osint-framework.git
cd osint-framework
docker compose up -d
```

---

Wait ~30 seconds for MySQL to initialize and migrations to run. Then open:

```
http://localhost:8000
```

You'll be redirected to **`/setup`** to create your first admin account. After that, the platform is ready with 8 pre-built investigation templates.

**What happens automatically on first start:**
1. MySQL 8 starts with persistent storage
2. Python engine starts (54 transforms, 4 generators, nmap included)
3. Laravel runs migrations + seeds 8 investigation templates
4. Web server starts on port 8000
5. Queue worker starts for async transform execution

To stop: `docker compose down` (data persists in Docker volumes).
To stop and wipe data: `docker compose down -v`.

### Docker environment

Customize by creating a `.env` file next to the compose file:

```env
MYSQL_ROOT_PASSWORD=your-secure-password
ENGINE_SHARED_SECRET=your-random-secret
WEB_PORT=8000
APP_NAME=Your Platform Name
```

## Manual setup (without Docker)

For development or when you need more control.

### Prerequisites

- PHP 8.3+ with extensions: pdo_mysql, bcmath, gd, zip
- Composer
- MySQL 8+ (or PostgreSQL — migrations are compatible)
- Python 3.11+
- nmap (optional, for network scanning transforms)

### 1. Python engine

```bash
cd osint-engine
python -m venv .venv

# Windows
.venv\Scripts\activate
# macOS / Linux
source .venv/bin/activate

pip install -e ".[dev,extras]"
```

### 2. Laravel application

```bash
cd osint-web
composer install
cp .env.example .env
php artisan key:generate
```

Create the database:

```sql
CREATE DATABASE osint_framework CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update `osint-web/.env` with your database credentials, then:

```bash
php artisan migrate
php artisan db:seed     # seeds 8 investigation templates
```

### 3. Start everything

One command starts all three processes (web server, queue worker, engine):

```bash
cd osint-web
composer run dev          # Windows
composer run dev:unix     # macOS / Linux
```

Open `http://localhost:8000` → redirected to `/setup` → create admin.

Alternatively, start each process separately:

```bash
# Terminal 1 — engine
cd osint-engine && python -m osint_engine.main

# Terminal 2 — web
cd osint-web && php artisan serve

# Terminal 3 — queue worker
cd osint-web && php artisan queue:work --tries=1
```

### 4. (Optional) demo data

```bash
cd osint-web
php artisan osint:demo
```

Creates a sample project with an `example.com` investigation graph, useful for exploring the UI.

---

## Architecture

```
Browser (Blade + Cytoscape.js)
    |
    |  HTTP + CSRF
    v
+-------------------+     +-------------------+
|  Laravel 13       |---->|  MySQL 8          |
|  (PHP 8.4)        |     |  (persistent)     |
|                   |     +-------------------+
|  Web server       |
|  Queue worker     |     +-------------------+
|                   |---->|  Python FastAPI    |
+-------------------+     |  Engine           |
   shared secret          |  (transforms,     |
   HTTP on :8077          |   generators)     |
                          +-------------------+
```

- **Laravel** handles all user-facing HTTP, authentication, graph persistence, job orchestration, and PDF generation
- **Python engine** discovers and executes transforms/generators dynamically, manages SSH slave connections
- **Queue worker** processes transform/template jobs asynchronously — the browser polls for results and streams nodes into the graph in real time
- Communication between Laravel and the engine uses a shared secret (`OSINT_ENGINE_SECRET` / `ENGINE_SHARED_SECRET`)

## Features in detail

### Investigation graphs

The core workspace. Create a project, open an investigation graph, and build out your entity map:

- **Right-click** any node → context menu shows compatible transforms grouped by category
- **Click a transform in the left sidebar** → runs it on selected node(s)
- **Keyboard shortcuts**: `S` = box-select mode, `P` = presentation mode, `Delete` = delete selected, `Ctrl+A` = select all, `Ctrl+K` = filter transforms
- **Layouts**: CoSE (force-directed), Breadthfirst (tree), Dagre (DAG), Klay (layered), Circle, Concentric, Grid
- **Minimap** with draggable viewport for navigating large graphs
- **Node inspector** (right sidebar) shows all entity data, parent/child navigation, and the full data payload from transforms
- **Presentation mode** hides sidebars, locks node positions, and dims the toolbar — clean view for sharing

### Transforms (50+)

| Category | Transforms |
|---|---|
| **Network** | dns.resolve, dns.reverse, whois.domain, crtsh.subdomains |
| **Web** | http.title, http.headers, http.robots, http.security_headers, http.favicon_hash |
| **Recon** | nmap.ping/quick/top100/top1000/all_tcp/udp_top50, nmap.service/os/vuln/ssl/http, web.feroxbuster, web.secrets_scan |
| **Snusbase** | email/username/password/hash/lastip/name/domain search, extract_*, hash_reverse, ip_whois |
| **Parsing** | email.to_domain, email.gravatar, email.validate, url.parse, url.to_domain, domain.tld, ip.classify, ip.geo |
| **Crypto** | string.hashes, string.entropy, string.b64decode |
| **Demo** | demo.echo, demo.split |

### Templates (8 pre-built)

Reusable investigation workflows executed as a visual DAG:

| Template | Input | What it does |
|---|---|---|
| **Passive recon** | domain | DNS → IP geo/classify/reverse, HTTP title/headers/security, WHOIS, crt.sh, TLD |
| **Active recon** | domain/IP | nmap top 100 → service/SSL/HTTP, favicon hash (requires slave) |
| **Person leak investigation** | email | Snusbase search → extract password/username/IP/hash → geo + hash crack |
| **Domain leak investigation** | domain | Snusbase domain search → extract all fields |
| **Web application recon** | domain/url | HTTP probes, security audit, secrets scan, crt.sh → DNS |
| **Domain recon** | domain | DNS + HTTP title + WHOIS (simple) |
| **Email recon** | email | Domain extraction + gravatar + DNS |
| **Offline demo** | any | Echo transform (engine smoke test) |

Build your own in the template editor: add input slots and transform steps visually, connect them with shift-click, and the runner handles topological execution with fan-out.

### Generators

Generators bridge uploaded files with transforms. Upload a wordlist via the File Manager, then use it with transforms like feroxbuster or DNS bruteforce:

| Generator | Input | Output |
|---|---|---|
| **seclists** | file | Wordlist content |
| **custom_wordlist** | text | Text pass-through |
| **ip_ranges** | file + text | Expanded CIDR ranges |
| **subdomain_list** | file + text | Combined prefix + domain FQDNs |

### SSH Slaves

Configure remote servers (or the local embedded server) for tool execution:

- **SSH slaves**: connect via password or private key (RSA, Ed25519, ECDSA auto-detected)
- **Embedded slave**: run commands locally on the engine server
- **Setup scripts**: define and run installation scripts (e.g., install nmap + feroxbuster) on slaves
- **Probe on connect**: auto-detects whoami, hostname, OS, public IP, country (with flag emoji), ISP

### Security

- **TOTP 2FA** via Google Authenticator (optional per user, with recovery codes)
- **Rate limiting** on login (5/min), setup (3/min), invite acceptance (5/min), TOTP challenge (5/min)
- **Password policy**: min 8 chars + mixed case + number
- **Invite-only registration**: 256-bit cryptographic tokens, one-shot, 72h expiry
- **Encrypted at rest**: API keys, slave credentials, TOTP secrets (AES-256-CBC via APP_KEY)
- **CSRF protection** on all state-changing endpoints
- **Engine isolation**: binds to 127.0.0.1, protected by shared secret, never exposed publicly

### SDK Documentation

Full documentation for writing custom transforms and generators is available at:

```
http://localhost:8000/docs
```

This is a public page (no login required) with search, covering: `@transform` decorator, Node/Edge classes, SlaveClient API, generators, API keys, entity types, and real-world examples.

## Writing custom transforms

Drop a `.py` file into `osint-engine/transforms/`:

```python
from osint_engine.sdk import transform, Node

@transform(
    name="my.probe",
    display_name="My probe",
    description="Does the thing",
    category="custom",
    input_types=["domain"],
    output_types=["note"],
    required_api_keys=["MY_KEY"],     # optional
    requires_slave=True,              # optional
    accepts_generator=True,           # optional
    timeout=60,
)
def run(node, api_keys, slave=None, generator_output=None):
    # node.type, node.value, node.data
    # api_keys["MY_KEY"] — decrypted from vault
    # slave.execute("nmap ...") — run on remote server
    # generator_output — string from a generator
    return [Node(type="note", value=f"found: {node.value}")]
```

Hit **Reload Engine** in the UI → your transform appears instantly. Or create transforms directly from the web editor at `/transformations/new`.

Third-party libraries: add to `osint-engine/pyproject.toml` → `[project.optional-dependencies] extras`, then `pip install -e ".[extras]"`.

## Running tests

```bash
# Python engine (71 tests)
cd osint-engine && pytest

# Laravel (43 tests)
cd osint-web && php artisan test
```

Laravel tests use SQLite in-memory (`phpunit.xml`), engine calls are faked with `Http::fake()`.

## Project structure

```
osint-framework/
├── docker-compose.yml          # Docker orchestration (4 services)
├── osint-web/                  # Laravel 13 application
│   ├── app/
│   │   ├── Http/Controllers/   # Auth, graphs, transforms, slaves, reports, ...
│   │   ├── Jobs/               # RunTransformJob, RunTemplateJob, GenerateReportJob, ...
│   │   ├── Models/             # User, Graph, GraphNode, Slave, ApiKey, ...
│   │   └── Services/           # EngineClient, TemplateRunner
│   ├── public/
│   │   ├── css/app.css         # Hacker theme (dark/light mode)
│   │   └── js/                 # graph.js (Cytoscape), editor.js (CodeMirror), app.js
│   ├── resources/views/        # Blade templates
│   │   ├── docs/               # SDK documentation (8 pages)
│   │   └── reports/            # PDF template
│   └── database/
│       ├── migrations/         # 19 migrations
│       └── seeders/            # TemplateSeeder (8 templates)
├── osint-engine/               # Python FastAPI engine
│   ├── osint_engine/
│   │   ├── sdk.py              # @transform decorator, Node, Edge
│   │   ├── generator_sdk.py    # @generator decorator, GeneratorInputs
│   │   ├── slave_client.py     # SSH + subprocess execution
│   │   ├── main.py             # FastAPI endpoints
│   │   ├── runner.py           # Transform execution with timeout
│   │   └── loader.py           # Dynamic module discovery
│   ├── transforms/             # 54 built-in transforms
│   └── generators/             # 4 built-in generators
└── README.md
```

## License

MIT
