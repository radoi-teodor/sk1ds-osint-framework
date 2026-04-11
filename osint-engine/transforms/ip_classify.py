"""Classify an IP address (private/loopback/link-local/reserved/public)."""

import ipaddress

from osint_engine.sdk import Node, transform


@transform(
    name="ip.classify",
    display_name="IP → classification",
    description="Determines whether an IP is private, loopback, link-local, multicast, reserved, or public.",
    category="parsing",
    input_types=["ipv4", "ipv6"],
    output_types=["note"],
    author="builtin",
)
def run(node, api_keys):
    try:
        ip = ipaddress.ip_address(node.value)
    except ValueError as exc:
        return [Node(type="note", value=f"invalid IP: {exc}", label="invalid")]

    flags = []
    if ip.is_private:       flags.append("private")
    if ip.is_loopback:      flags.append("loopback")
    if ip.is_link_local:    flags.append("link-local")
    if ip.is_multicast:     flags.append("multicast")
    if ip.is_reserved:      flags.append("reserved")
    if ip.is_unspecified:   flags.append("unspecified")
    if not flags:           flags.append("public")

    return [
        Node(
            type="note",
            value=f"{ip.version==4 and 'IPv4' or 'IPv6'}: {', '.join(flags)}",
            label=", ".join(flags),
            data={"version": ip.version, "flags": flags, "reverse_pointer": ip.reverse_pointer},
        )
    ]
