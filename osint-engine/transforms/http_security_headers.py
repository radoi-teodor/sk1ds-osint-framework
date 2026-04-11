"""Check the target for common security-related HTTP headers."""

import urllib.request

from osint_engine.sdk import Node, transform


_CHECKS = [
    "strict-transport-security",
    "content-security-policy",
    "x-frame-options",
    "x-content-type-options",
    "referrer-policy",
    "permissions-policy",
    "cross-origin-opener-policy",
    "cross-origin-resource-policy",
    "cross-origin-embedder-policy",
]


@transform(
    name="http.security_headers",
    display_name="Security header audit",
    description="Requests the target and reports which of the common security headers are present, missing, or weak.",
    category="web",
    input_types=["domain", "url"],
    output_types=["note"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    url = node.value if node.value.startswith(("http://", "https://")) else f"https://{node.value}"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            headers = {k.lower(): v for k, v in resp.headers.items()}
    except Exception as exc:
        return [Node(type="note", value=f"unreachable: {exc}", label="unreachable")]

    out = []
    for name in _CHECKS:
        if name in headers:
            out.append(Node(
                type="note",
                value=f"OK  {name}: {headers[name][:120]}",
                label=f"✓ {name}",
                data={"status": "present", "header": name, "value": headers[name]},
            ))
        else:
            out.append(Node(
                type="note",
                value=f"MISSING {name}",
                label=f"✗ {name}",
                data={"status": "missing", "header": name},
            ))
    return out
