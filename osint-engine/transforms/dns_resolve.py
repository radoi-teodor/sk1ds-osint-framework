"""Resolve a domain to IPv4 addresses using stdlib socket."""

import socket

from osint_engine.sdk import Node, transform


@transform(
    name="dns.resolve",
    display_name="DNS → A records",
    description="Resolves a domain to IPv4 addresses via socket.gethostbyname_ex.",
    category="network",
    input_types=["domain"],
    output_types=["ipv4"],
    timeout=10,
    author="builtin",
)
def run(node, api_keys):
    try:
        _, _, ips = socket.gethostbyname_ex(node.value)
    except (socket.gaierror, socket.herror, UnicodeError):
        return []
    return [Node(type="ipv4", value=ip, label=ip) for ip in ips]
