"""Minimal WHOIS lookup over port 43, stdlib-only.

Produces a whois_record node (with the raw response in `data.raw`) and
any email addresses found in the response as `email` nodes.
"""

import re
import socket

from osint_engine.sdk import Node, transform


_EMAIL_RE = re.compile(r"[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}")


def _query(server: str, q: str) -> str:
    with socket.create_connection((server, 43), timeout=10) as s:
        s.sendall((q + "\r\n").encode())
        chunks = []
        while True:
            chunk = s.recv(4096)
            if not chunk:
                break
            chunks.append(chunk)
    return b"".join(chunks).decode("utf-8", errors="replace")


@transform(
    name="whois.domain",
    display_name="WHOIS lookup",
    description="Queries whois.iana.org, follows the 'refer:' hint, returns raw text + extracted emails.",
    category="network",
    input_types=["domain"],
    output_types=["whois_record", "email"],
    timeout=20,
    author="builtin",
)
def run(node, api_keys):
    try:
        raw = _query("whois.iana.org", node.value)
        refer = None
        for line in raw.splitlines():
            if line.lower().startswith("refer:"):
                refer = line.split(":", 1)[1].strip()
                break
        if refer:
            raw = _query(refer, node.value)
    except Exception:
        return []

    out = [
        Node(
            type="whois_record",
            value=node.value,
            label=f"WHOIS {node.value}",
            data={"raw": raw[:4000]},
        )
    ]
    emails = sorted(set(_EMAIL_RE.findall(raw)))
    for em in emails[:10]:
        out.append(Node(type="email", value=em, label=em))
    return out
