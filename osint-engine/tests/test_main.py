from fastapi.testclient import TestClient

from osint_engine.config import settings
from osint_engine.main import app

HEADERS = {"X-Engine-Secret": settings.shared_secret}


def _client() -> TestClient:
    return TestClient(app)


def test_health_no_auth():
    with _client() as c:
        r = c.get("/health")
    assert r.status_code == 200
    assert r.json()["status"] == "ok"


def test_list_requires_secret():
    with _client() as c:
        r = c.get("/transforms")
    assert r.status_code == 401


def test_list_transforms():
    with _client() as c:
        r = c.get("/transforms", headers=HEADERS)
    assert r.status_code == 200
    names = [t["name"] for t in r.json()["transforms"]]
    assert "demo.echo" in names
    assert "dns.resolve" in names


def test_run_demo_echo():
    with _client() as c:
        r = c.post(
            "/transforms/demo.echo/run",
            headers=HEADERS,
            json={"node": {"type": "domain", "value": "example.com"}, "api_keys": {}},
        )
    assert r.status_code == 200
    data = r.json()
    assert data["error"] is None
    assert data["nodes"][0]["value"] == "echo:example.com"


def test_run_unknown_transform():
    with _client() as c:
        r = c.post(
            "/transforms/nope.nada/run",
            headers=HEADERS,
            json={"node": {"type": "domain", "value": "x"}, "api_keys": {}},
        )
    assert r.status_code == 404


def test_run_wrong_input_type():
    with _client() as c:
        r = c.post(
            "/transforms/email.to_domain/run",
            headers=HEADERS,
            json={"node": {"type": "domain", "value": "example.com"}, "api_keys": {}},
        )
    assert r.status_code == 400


def test_validate_endpoint():
    with _client() as c:
        good = c.post("/validate", headers=HEADERS, json={"source": "x = 1\n"})
        bad = c.post("/validate", headers=HEADERS, json={"source": "def (:\n"})
    assert good.json()["valid"] is True
    assert bad.json()["valid"] is False


def test_get_source():
    with _client() as c:
        r = c.get("/transforms/demo.echo/source", headers=HEADERS)
    assert r.status_code == 200
    body = r.json()
    assert body["filename"] == "demo_echo.py"
    assert "@transform" in body["source"]
