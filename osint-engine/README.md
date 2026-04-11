# osint-engine

Dynamic transformation engine for the OSINT framework. Laravel talks to this
service over HTTP. This process should **never** be exposed publicly — bind it
to 127.0.0.1 and keep `ENGINE_SHARED_SECRET` in sync with the Laravel `.env`.

## Quick start (Windows, Python 3.11+)

```bash
cd osint-engine
python -m venv .venv
.venv\Scripts\activate
pip install -e .[dev,extras]
set ENGINE_SHARED_SECRET=dev-secret-change-me
python -m osint_engine.main
```

The service listens on `http://127.0.0.1:8077` by default.

## Environment

| Variable | Default | Description |
|----------|---------|-------------|
| `ENGINE_HOST` | `127.0.0.1` | bind host |
| `ENGINE_PORT` | `8077` | bind port |
| `ENGINE_SHARED_SECRET` | `dev-secret-change-me` | shared with Laravel |
| `ENGINE_TRANSFORMS_DIR` | `./transforms` | where to scan for `*.py` transforms |

## Writing a transform

Drop a `*.py` file into `transforms/`:

```python
from osint_engine.sdk import Node, transform

@transform(
    name="my.thing",
    display_name="My Thing",
    description="What it does",
    category="custom",
    input_types=["domain"],
    output_types=["ipv4"],
    required_api_keys=["MY_API_KEY"],  # optional
    timeout=10,
)
def run(node, api_keys):
    key = api_keys.get("MY_API_KEY")
    # ... do work ...
    return [Node(type="ipv4", value="1.2.3.4")]
```

Then `POST /reload` (the Laravel UI does this automatically after saving).

### Using third-party libraries

Add them to `pyproject.toml` under `[project.optional-dependencies] extras` and
run `pip install -e .[extras]`. Then `import` them from your transform.
`requests`, `dnspython`, `python-whois`, and `beautifulsoup4` are already listed.

## Tests

```bash
pytest
```

The offline-only tests don't require network access. Network-dependent
built-ins (DNS, WHOIS, HTTP title) are tested through the registry without
actually hitting the network.
