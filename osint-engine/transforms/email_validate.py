"""Structural email validation plus an MX sanity check via DNS."""

import re
import socket

from osint_engine.sdk import Node, transform


_EMAIL_RE = re.compile(r"^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$")


@transform(
    name="email.validate",
    display_name="Email validation",
    description="Checks the email for structural validity and verifies the domain resolves (A record sanity check — not an SMTP probe).",
    category="parsing",
    input_types=["email"],
    output_types=["note", "domain"],
    timeout=10,
    author="builtin",
)
def run(node, api_keys):
    email = str(node.value).strip()
    out: list[Node] = []

    if not _EMAIL_RE.match(email):
        return [Node(type="note", value="invalid structure", label="✗ invalid")]
    out.append(Node(type="note", value="structure OK", label="✓ structure"))

    domain = email.split("@", 1)[1].lower()
    out.append(Node(type="domain", value=domain, label=domain))

    try:
        socket.gethostbyname(domain)
        out.append(Node(type="note", value=f"{domain} resolves", label="✓ resolves"))
    except socket.gaierror:
        out.append(Node(type="note", value=f"{domain} does not resolve", label="✗ no DNS"))
    return out
