"""Snusbase integration — breach search, hash reverse, IP WHOIS, extractors.

Design:
  - Each breach record is emitted as ONE node (not split into fields). The
    full record is attached in `data.record`, the breach name in `data.breach`.
  - The node's `type` is chosen as the primary identity of the record
    (email > username > ipv4 > hash > person), falling back to `note`.
  - To pivot on a specific field (hash, password, ip, etc.) the user runs
    one of the offline `snusbase.extract_*` transforms on the result node.
    Extractors read from `data.record` and emit a typed node while keeping
    the source record attached, so the chain of extractions is lossless.

Requires API key SNUSBASE_API_KEY for search endpoints.
Docs: https://docs.snusbase.com/
"""

from __future__ import annotations

import json
import urllib.error
import urllib.request
from typing import Any

from osint_engine.sdk import Node, transform


_SEARCH_URL = "https://api.snusbase.com/data/search"
_HASH_LOOKUP_URL = "https://api.snusbase.com/tools/hash-lookup"
_IP_WHOIS_URL = "https://api.snusbase.com/tools/ip-whois"


# ---------- helpers ----------

def _post(url: str, api_key: str, payload: dict) -> dict:
    body = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Auth": api_key,
            "Content-Type": "application/json",
            "User-Agent": "osint-engine/0.1",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode("utf-8", errors="replace"))
    except urllib.error.HTTPError as exc:
        # Capture the response body — Snusbase often explains the error there.
        err_body = ""
        try:
            err_body = exc.read().decode("utf-8", errors="replace")[:500]
        except Exception:
            pass
        raise RuntimeError(f"HTTP {exc.code}: {err_body or exc.reason}") from exc


def _missing_key_node() -> list[Node]:
    return [Node(
        type="note",
        value="missing API key SNUSBASE_API_KEY",
        label="missing SNUSBASE_API_KEY",
    )]


def _error_node(msg: str) -> list[Node]:
    return [Node(type="note", value=f"snusbase error: {msg}", label="snusbase error")]


def _clean_record(rec: dict) -> dict:
    return {k: v for k, v in rec.items() if v not in (None, "", [], {})}


# Primary identity chain: first match wins.
_IDENTITY_CHAIN: list[tuple[str, str]] = [
    ("email", "email"),
    ("username", "username"),
    ("lastip", "ipv4"),
    ("hash", "hash"),
    ("name", "person"),
]


def _primary_identity(rec: dict) -> tuple[str, str] | None:
    for field, type_ in _IDENTITY_CHAIN:
        v = rec.get(field)
        if v not in (None, "", [], {}):
            return type_, str(v)
    return None


def _short_breach(name: str, max_len: int = 34) -> str:
    return name if len(name) <= max_len else name[:max_len - 1] + "…"


def _nodes_from_search(results: dict) -> list[Node]:
    """Walk a /data/search response and emit one node per record.

    Response shape:
        { "results": { "BreachName_2020": [ { "email": "...", ... }, ... ], ... } }

    Every record produces exactly one node whose `data.record` preserves all
    original fields. No field-splitting, no dedup — records from different
    breaches with identical values remain distinct entities.
    """
    out: list[Node] = []

    for breach_name, records in (results or {}).items():
        if isinstance(records, dict):
            records = [records]
        if not isinstance(records, list):
            continue

        for rec in records:
            if not isinstance(rec, dict):
                continue
            clean = _clean_record(rec)
            if not clean:
                continue
            ident = _primary_identity(clean)
            if ident is None:
                # Record had nothing identity-ish — keep it as a raw note.
                out.append(Node(
                    type="note",
                    value=json.dumps(clean, ensure_ascii=False)[:400],
                    label=f"record · {_short_breach(breach_name)}",
                    data={"breach": breach_name, "record": clean, "source": "snusbase"},
                ))
                continue

            type_, value = ident
            label = f"{value} · {_short_breach(breach_name)}"
            out.append(Node(
                type=type_,
                value=value,
                label=label,
                data={
                    "breach": breach_name,
                    "record": clean,
                    "source": "snusbase",
                },
            ))

    return out


def _search(api_key: str, term: str, snusbase_type: str) -> list[Node]:
    try:
        data = _post(_SEARCH_URL, api_key, {"terms": [term], "types": [snusbase_type]})
    except Exception as exc:
        return _error_node(str(exc))
    if not isinstance(data, dict):
        return _error_node("unexpected response shape")
    # Snusbase may return {"errors": ["..."]} with HTTP 200.
    if "errors" in data:
        errs = data["errors"]
        msg = ", ".join(errs) if isinstance(errs, list) else str(errs)
        return _error_node(f"Snusbase API: {msg}")
    if "results" not in data:
        return _error_node("unexpected response (no results key)")
    nodes = _nodes_from_search(data.get("results") or {})
    if not nodes:
        return [Node(
            type="note",
            value="no breach hits",
            label="no hits",
            data={"size": data.get("size", 0), "took": data.get("took", 0)},
        )]
    return nodes


# ---------- /data/search transforms (one node per record) ----------

@transform(
    name="snusbase.email",
    display_name="Snusbase: email → records",
    description="Searches Snusbase by email. Each breach record is returned as a separate node carrying the full record in data.record.",
    category="snusbase",
    input_types=["email"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_email(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "email")


@transform(
    name="snusbase.username",
    display_name="Snusbase: username → records",
    description="Searches Snusbase by username. One node per breach record.",
    category="snusbase",
    input_types=["username"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_username(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "username")


@transform(
    name="snusbase.password",
    display_name="Snusbase: password → records",
    description="Reverse pivot — finds breach records that used this plaintext password. One node per record.",
    category="snusbase",
    input_types=["password"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_password(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "password")


@transform(
    name="snusbase.hash",
    display_name="Snusbase: hash → records",
    description="Finds breach records containing this hash. One node per record.",
    category="snusbase",
    input_types=["hash"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_hash(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "hash")


@transform(
    name="snusbase.lastip",
    display_name="Snusbase: IP → records",
    description="Finds breach records whose lastip matches. One node per record.",
    category="snusbase",
    input_types=["ipv4", "ipv6"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_lastip(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "lastip")


@transform(
    name="snusbase.name",
    display_name="Snusbase: person → records",
    description="Searches by real name. One node per record.",
    category="snusbase",
    input_types=["person"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
    author="builtin",
)
def run_name(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "name")


@transform(
    name="snusbase.domain",
    display_name="Snusbase: domain → records",
    description="Returns every breach record whose email belongs to this domain. Each record becomes one email node with the full record attached.",
    category="snusbase",
    input_types=["domain"],
    output_types=["email", "username", "ipv4", "hash", "person", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=60,
    author="builtin",
)
def run_domain(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    return _search(key, node.value, "_domain")


# ---------- extractors (offline, pivot on fields already in data.record) ----------

def _get_record(node) -> dict | None:
    data = node.data if isinstance(node.data, dict) else {}
    rec = data.get("record")
    return rec if isinstance(rec, dict) else None


def _extract_one(node, field: str, out_type: str) -> list[Node]:
    rec = _get_record(node)
    if rec is None:
        return [Node(type="note", value="no Snusbase record attached", label="no record")]
    val = rec.get(field)
    if val in (None, "", [], {}):
        return [Node(type="note", value=f"no `{field}` in record", label=f"no {field}")]
    breach = (node.data or {}).get("breach", "")
    return [Node(
        type=out_type,
        value=str(val),
        label=f"{val}" + (f" · {_short_breach(breach)}" if breach else ""),
        data={
            "breach": breach,
            "record": rec,
            "source": "snusbase.extract",
            "extracted_from_field": field,
        },
    )]


@transform(
    name="snusbase.extract_hash",
    display_name="Extract hash from record",
    description="Reads `data.record.hash` from a Snusbase-sourced node and emits it as a hash node. Offline — no API call.",
    category="snusbase",
    input_types=["*"],
    output_types=["hash", "note"],
    author="builtin",
)
def run_extract_hash(node, api_keys):
    return _extract_one(node, "hash", "hash")


@transform(
    name="snusbase.extract_password",
    display_name="Extract password from record",
    description="Reads `data.record.password` and emits it as a password node. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["password", "note"],
    author="builtin",
)
def run_extract_password(node, api_keys):
    return _extract_one(node, "password", "password")


@transform(
    name="snusbase.extract_username",
    display_name="Extract username from record",
    description="Reads `data.record.username` and emits it as a username node. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["username", "note"],
    author="builtin",
)
def run_extract_username(node, api_keys):
    return _extract_one(node, "username", "username")


@transform(
    name="snusbase.extract_email",
    display_name="Extract email from record",
    description="Reads `data.record.email` and emits it as an email node. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["email", "note"],
    author="builtin",
)
def run_extract_email(node, api_keys):
    return _extract_one(node, "email", "email")


@transform(
    name="snusbase.extract_lastip",
    display_name="Extract IP from record",
    description="Reads `data.record.lastip` and emits it as an ipv4 node. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["ipv4", "note"],
    author="builtin",
)
def run_extract_lastip(node, api_keys):
    return _extract_one(node, "lastip", "ipv4")


@transform(
    name="snusbase.extract_name",
    display_name="Extract name from record",
    description="Reads `data.record.name` and emits it as a person node. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["person", "note"],
    author="builtin",
)
def run_extract_name(node, api_keys):
    return _extract_one(node, "name", "person")


@transform(
    name="snusbase.extract_all",
    display_name="Explode record into all fields",
    description="Reads every known field from `data.record` and emits a typed node for each non-empty one. Offline.",
    category="snusbase",
    input_types=["*"],
    output_types=["email", "username", "password", "hash", "ipv4", "person", "note"],
    author="builtin",
)
def run_extract_all(node, api_keys):
    rec = _get_record(node)
    if rec is None:
        return [Node(type="note", value="no Snusbase record attached", label="no record")]
    breach = (node.data or {}).get("breach", "")
    field_map = [
        ("email", "email"),
        ("username", "username"),
        ("password", "password"),
        ("hash", "hash"),
        ("lastip", "ipv4"),
        ("name", "person"),
    ]
    out: list[Node] = []
    for field, type_ in field_map:
        val = rec.get(field)
        if val in (None, "", [], {}):
            continue
        out.append(Node(
            type=type_,
            value=str(val),
            label=f"{val}" + (f" · {_short_breach(breach)}" if breach else ""),
            data={
                "breach": breach,
                "record": rec,
                "source": "snusbase.extract_all",
                "extracted_from_field": field,
            },
        ))
    # Auxiliary fields surfaced as notes.
    aux = ("phone", "address", "city", "country", "regdate", "birthdate", "dob", "firstname", "lastname", "company", "salt", "uid", "created", "updated")
    for k in aux:
        if rec.get(k):
            out.append(Node(
                type="note",
                value=f"{k}: {rec[k]}",
                label=f"{k}: {rec[k]}",
                data={"breach": breach, "record": rec, "field": k, "source": "snusbase.extract_all"},
            ))
    if not out:
        return [Node(type="note", value="record empty after extraction", label="empty")]
    return out


# ---------- /tools/hash-lookup ----------

@transform(
    name="snusbase.hash_reverse",
    display_name="Snusbase: hash → plaintext",
    description="Dedicated hash-cracking lookup (hash → plaintext password). Faster than /data/search for raw cracking.",
    category="snusbase",
    input_types=["hash"],
    output_types=["password", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=20,
    author="builtin",
)
def run_hash_reverse(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    try:
        data = _post(_HASH_LOOKUP_URL, key, {"terms": [node.value], "types": ["hash"]})
    except Exception as exc:
        return _error_node(str(exc))

    results = (data or {}).get("results") or {}
    hashes_table = results.get("HASHES") or []
    out: list[Node] = []
    seen: set[str] = set()
    for row in hashes_table:
        pw = row.get("password")
        if not pw or pw in seen:
            continue
        seen.add(pw)
        out.append(Node(
            type="password",
            value=pw,
            label=pw,
            data={"source": "snusbase.hash_reverse", "hash": node.value},
        ))
    if not out:
        return [Node(type="note", value="hash not cracked", label="no plaintext")]
    return out


# ---------- /tools/ip-whois ----------

@transform(
    name="snusbase.ip_whois",
    display_name="Snusbase: IP WHOIS",
    description="Geolocation + ISP lookup via Snusbase. Emits location + organization.",
    category="snusbase",
    input_types=["ipv4", "ipv6"],
    output_types=["location", "organization", "asn", "note"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=20,
    author="builtin",
)
def run_ip_whois(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key: return _missing_key_node()
    try:
        data = _post(_IP_WHOIS_URL, key, {"terms": [node.value]})
    except Exception as exc:
        return _error_node(str(exc))

    results = (data or {}).get("results") or {}
    record = results.get(node.value) or {}
    if not record:
        return [Node(type="note", value="no whois data", label="no data")]

    out: list[Node] = []
    country = record.get("country")
    city = record.get("city")
    region = record.get("regionName") or record.get("region")
    if country or city:
        loc_value = ", ".join(filter(None, [city, region, country]))
        out.append(Node(
            type="location",
            value=loc_value,
            label=loc_value,
            data={k: record.get(k) for k in ("country", "countryCode", "city", "region", "regionName", "lat", "lon", "zip", "timezone") if record.get(k) is not None},
        ))
    if record.get("isp"):
        out.append(Node(type="organization", value=record["isp"], label=f"ISP: {record['isp']}"))
    if record.get("org") and record.get("org") != record.get("isp"):
        out.append(Node(type="organization", value=record["org"], label=f"Org: {record['org']}"))
    if record.get("as"):
        out.append(Node(type="asn", value=record["as"], label=record.get("asname") or record["as"]))
    if not out:
        out.append(Node(type="note", value="empty whois", label="empty"))
    return out
