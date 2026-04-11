import asyncio
from pathlib import Path

from osint_engine.loader import load_transforms
from osint_engine.runner import run_transform
from osint_engine.sdk import Node, clear_registry, get_registry, transform

TRANSFORMS_DIR = Path(__file__).parent.parent / "transforms"


def test_run_transform_success():
    clear_registry()
    load_transforms(TRANSFORMS_DIR)
    spec = get_registry()["email.to_domain"]
    result = asyncio.run(run_transform(spec, Node(type="email", value="alice@example.com"), {}))
    assert result["error"] is None
    assert len(result["nodes"]) == 1
    assert result["nodes"][0]["value"] == "example.com"


def test_run_transform_catches_exception():
    clear_registry()

    @transform(name="test.fail", display_name="Fail")
    def run(node, api_keys):
        raise ValueError("boom")

    spec = get_registry()["test.fail"]
    result = asyncio.run(run_transform(spec, Node(type="x", value="y"), {}))
    assert "boom" in result["error"]
    assert result["nodes"] == []


def test_run_transform_timeout():
    clear_registry()

    @transform(name="test.slow", display_name="Slow", timeout=1)
    def run(node, api_keys):
        import time
        time.sleep(3)
        return []

    spec = get_registry()["test.slow"]
    result = asyncio.run(run_transform(spec, Node(type="x", value="y"), {}))
    assert "timed out" in result["error"]
