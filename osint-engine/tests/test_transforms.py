"""Tests for the built-in transform functions (offline only)."""

from pathlib import Path

from osint_engine.loader import load_transforms
from osint_engine.sdk import Node, clear_registry, get_registry

TRANSFORMS_DIR = Path(__file__).parent.parent / "transforms"


def setup_module(_module):
    clear_registry()
    load_transforms(TRANSFORMS_DIR)


def _run(name: str, node: Node, api_keys: dict | None = None):
    return get_registry()[name].func(node, api_keys or {})


def test_email_to_domain():
    result = _run("email.to_domain", Node(type="email", value="Alice@Example.COM"))
    assert len(result) == 1
    assert result[0].value == "example.com"
    assert result[0].type == "domain"


def test_email_to_domain_invalid():
    assert _run("email.to_domain", Node(type="email", value="no-at-sign")) == []


def test_email_gravatar_hash():
    result = _run("email.gravatar", Node(type="email", value="test@example.com"))
    # MD5 of "test@example.com" lowercased
    assert "55502f40dc8b7c769880b10874abc9d0" in result[0].value


def test_demo_echo():
    result = _run("demo.echo", Node(type="domain", value="example.com"))
    assert len(result) == 1
    assert result[0].value == "echo:example.com"
    assert result[0].data["source_type"] == "domain"


def test_demo_split():
    result = _run("demo.split", Node(type="note", value="a, b, c"))
    assert [n.value for n in result] == ["a", "b", "c"]


def test_url_to_domain():
    result = _run("url.to_domain", Node(type="url", value="https://foo.example.com/path?q=1"))
    assert result[0].value == "foo.example.com"


def test_c99_missing_key():
    result = _run("c99.phone_lookup", Node(type="phone", value="+10000000000"), {})
    assert any("missing" in n.value for n in result)
