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


# ---- snusbase ----

def test_snusbase_missing_key():
    for name in ("snusbase.email", "snusbase.username", "snusbase.password",
                 "snusbase.hash", "snusbase.lastip", "snusbase.name",
                 "snusbase.domain", "snusbase.hash_reverse", "snusbase.ip_whois"):
        result = _run(name, Node(type="email", value="alice@example.com"), {})
        assert result
        assert any("missing" in n.value.lower() for n in result), f"{name} missing-key path"


def _snusbase_mod():
    from osint_engine.loader import load_transforms
    load_transforms(TRANSFORMS_DIR)
    import sys
    m = sys.modules.get("osint_transforms_snusbase")
    assert m is not None
    return m


def test_snusbase_one_node_per_record():
    mod = _snusbase_mod()
    fake = {
        "TestBreach_2020": [
            {"email": "alice@example.com", "password": "hunter2", "username": "alice", "lastip": "1.2.3.4", "uid": "123"},
            {"email": "alice@example.com", "password": "hunter2", "hash": "abc123", "salt": "s"},
        ],
        "AnotherLeak_2022": [
            {"email": "alice@example.com", "password": "pass1", "name": "Alice Doe", "phone": "+1234"},
        ],
    }
    nodes = mod._nodes_from_search(fake)
    # 3 records total → 3 nodes (no dedup)
    assert len(nodes) == 3
    # All primary type = email because every record has an email field
    assert all(n.type == "email" for n in nodes)
    # No breach nodes produced
    assert not any(n.type == "breach" for n in nodes)
    # Each node carries the full source record
    rec0 = nodes[0].data["record"]
    assert rec0["password"] == "hunter2"
    assert rec0["lastip"] == "1.2.3.4"
    assert rec0["uid"] == "123"
    assert nodes[0].data["breach"] == "TestBreach_2020"
    # Label includes a short breach hint
    assert "TestBreach_2020" in nodes[0].label
    # Third record is from the other breach
    assert nodes[2].data["breach"] == "AnotherLeak_2022"
    assert nodes[2].data["record"]["name"] == "Alice Doe"


def test_snusbase_primary_identity_fallback():
    mod = _snusbase_mod()
    # Record without email but with username
    fake = {"B": [{"username": "avvd", "hash": "xyz"}]}
    nodes = mod._nodes_from_search(fake)
    assert len(nodes) == 1
    assert nodes[0].type == "username"
    assert nodes[0].value == "avvd"
    assert nodes[0].data["record"]["hash"] == "xyz"


def test_snusbase_primary_identity_ip_only():
    mod = _snusbase_mod()
    fake = {"B": [{"lastip": "8.8.8.8", "uid": "42"}]}
    nodes = mod._nodes_from_search(fake)
    assert len(nodes) == 1
    assert nodes[0].type == "ipv4"
    assert nodes[0].value == "8.8.8.8"


def test_snusbase_empty_results():
    mod = _snusbase_mod()
    assert mod._nodes_from_search({}) == []
    assert mod._nodes_from_search({"Empty": []}) == []


def test_snusbase_extract_hash():
    source = Node(
        type="email",
        value="alice@example.com",
        label="alice@example.com",
        data={
            "breach": "TestBreach",
            "record": {"email": "alice@example.com", "hash": "deadbeef", "password": "hunter2", "salt": "x"},
        },
    )
    result = _run("snusbase.extract_hash", source)
    assert len(result) == 1
    assert result[0].type == "hash"
    assert result[0].value == "deadbeef"
    assert result[0].data["extracted_from_field"] == "hash"
    assert result[0].data["record"]["password"] == "hunter2"  # record context preserved


def test_snusbase_extract_password_missing():
    source = Node(type="email", value="a@b.com", data={"breach": "B", "record": {"email": "a@b.com"}})
    result = _run("snusbase.extract_password", source)
    assert result[0].type == "note"
    assert "no `password`" in result[0].value


def test_snusbase_extract_without_record():
    source = Node(type="email", value="a@b.com", data={})
    result = _run("snusbase.extract_hash", source)
    assert result[0].type == "note"
    assert "no Snusbase record" in result[0].value


def test_snusbase_extract_all():
    rec = {
        "email": "a@b.com", "username": "au", "password": "pw",
        "hash": "hh", "lastip": "1.2.3.4", "name": "A B",
        "phone": "+1234", "uid": "99",
    }
    source = Node(type="email", value="a@b.com", data={"breach": "B", "record": rec})
    result = _run("snusbase.extract_all", source)
    types = sorted({n.type for n in result})
    # Known-typed fields
    assert "email" in types
    assert "username" in types
    assert "password" in types
    assert "hash" in types
    assert "ipv4" in types
    assert "person" in types
    # Aux fields become notes
    assert any(n.type == "note" and "phone" in n.value for n in result)
    assert any(n.type == "note" and "uid" in n.value for n in result)
