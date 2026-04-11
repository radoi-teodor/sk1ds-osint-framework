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


# ---- new built-in transforms ----

def test_ip_classify_private():
    result = _run("ip.classify", Node(type="ipv4", value="10.0.0.1"))
    assert result
    assert "private" in result[0].value


def test_ip_classify_public():
    result = _run("ip.classify", Node(type="ipv4", value="8.8.8.8"))
    assert "public" in result[0].value


def test_ip_classify_invalid():
    result = _run("ip.classify", Node(type="ipv4", value="not-an-ip"))
    assert "invalid" in result[0].label


def test_url_parse():
    result = _run("url.parse", Node(type="url", value="https://api.example.com:8443/v1/users?id=42&sort=asc#top"))
    types = [n.type for n in result]
    values = " | ".join(n.value for n in result)
    assert "domain" in types
    assert "port" in types
    assert "api.example.com" in values
    assert "8443" in values
    assert "id=42" in values


def test_string_hashes():
    result = _run("string.hashes", Node(type="note", value="abc"))
    algos = {n.data.get("algo") for n in result}
    assert algos == {"md5", "sha1", "sha256", "sha512"}
    # md5("abc") = 900150983cd24fb0d6963f7d28e17f72
    md5 = next(n.value for n in result if n.data["algo"] == "md5")
    assert md5 == "900150983cd24fb0d6963f7d28e17f72"


def test_string_b64decode_text():
    # "hello world" base64
    result = _run("string.b64decode", Node(type="note", value="aGVsbG8gd29ybGQ="))
    assert "hello world" in result[0].value


def test_string_b64decode_invalid():
    result = _run("string.b64decode", Node(type="note", value="!!!not base64!!!"))
    assert "not b64" in result[0].label or "not base64" in result[0].value


def test_string_entropy_high():
    result = _run("string.entropy", Node(type="hash", value="a1b2c3d4e5f67890abcdef1234567890"))
    entropy = result[0].data["entropy"]
    assert entropy > 3.5


def test_string_entropy_low():
    result = _run("string.entropy", Node(type="note", value="aaaaaaaaaaaa"))
    entropy = result[0].data["entropy"]
    assert entropy < 1.0


def test_email_validate_good():
    result = _run("email.validate", Node(type="email", value="alice@example.com"))
    labels = [n.label for n in result]
    assert any("structure" in l for l in labels)


def test_email_validate_bad():
    result = _run("email.validate", Node(type="email", value="no-at-sign"))
    assert "invalid" in result[0].label


def test_domain_tld_simple():
    result = _run("domain.tld", Node(type="domain", value="api.example.com"))
    values = [n.value for n in result]
    assert "example.com" in values
    assert any("tld: com" in v for v in values)


def test_domain_tld_twolevel():
    result = _run("domain.tld", Node(type="domain", value="api.foo.co.uk"))
    values = [n.value for n in result]
    assert "foo.co.uk" in values
    assert any("co.uk" in v for v in values)
