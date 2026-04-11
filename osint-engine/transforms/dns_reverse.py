"""Reverse DNS lookup for an IP address."""

import socket

from osint_engine.sdk import Node, transform


@transform(
    name="dns.reverse",
    display_name="Reverse DNS",
    description="Resolves an IP to a hostname via socket.gethostbyaddr.",
    category="network",
    input_types=["ipv4", "ipv6"],
    output_types=["domain"],
    timeout=10,
    author="builtin",
)
def run(node, api_keys):
    try:
        host, _, _ = socket.gethostbyaddr(node.value)
    except (socket.herror, socket.gaierror):
        return []
    return [Node(type="domain", value=host, label=host)]
