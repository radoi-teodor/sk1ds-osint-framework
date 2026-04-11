"""Enumerate subdomains from crt.sh Certificate Transparency logs."""

import json
import urllib.parse
import urllib.request

from osint_engine.sdk import Node, transform


@transform(
    name="crtsh.subdomains",
    display_name="crt.sh → subdomains",
    description="Queries https://crt.sh certificate transparency logs for subdomains of the input domain.",
    category="network",
    input_types=["domain"],
    output_types=["domain"],
    timeout=45,
    author="builtin",
)
def run(node, api_keys):
    q = urllib.parse.quote(f"%.{node.value}")
    url = f"https://crt.sh/?q={q}&output=json"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=40) as resp:
            body = resp.read().decode("utf-8", errors="replace")
        rows = json.loads(body)
    except Exception as exc:
        return [Node(type="note", value=f"crt.sh error: {exc}", label="crt.sh error")]

    found: set[str] = set()
    for row in rows:
        name_value = row.get("name_value", "")
        for line in name_value.splitlines():
            line = line.strip().lower().lstrip("*.")
            if line and line.endswith(node.value.lower()) and line != node.value.lower():
                found.add(line)

    # Cap at 200 to avoid flooding the graph on huge wildcards.
    return [Node(type="domain", value=d, label=d) for d in sorted(found)[:200]]
