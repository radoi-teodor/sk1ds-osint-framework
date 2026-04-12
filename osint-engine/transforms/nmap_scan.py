"""Nmap top-100 port scan via a slave SSH connection or local subprocess."""

import re

from osint_engine.sdk import Node, transform

_SAFE_TARGET = re.compile(r"^[a-zA-Z0-9.\-:]+$")


@transform(
    name="nmap.top100",
    display_name="Nmap: top 100 ports",
    description="Scans the target's top 100 TCP ports using nmap on the configured slave. Requires nmap installed on the slave.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=120,
    author="builtin",
)
def run(node, api_keys, slave):
    target = node.value.strip()
    if not _SAFE_TARGET.match(target):
        return [Node(type="note", value=f"unsafe target value: {target}", label="bad target")]

    result = slave.execute(f"nmap -T4 --top-ports 100 -oG - {target}", timeout=90)
    if not result.ok:
        return [Node(
            type="note",
            value=f"nmap error (exit {result.exit_code}): {result.stderr[:300]}",
            label="nmap error",
        )]

    ports = []
    for line in result.stdout.splitlines():
        if "/open/" not in line:
            continue
        # Greppable format: Host: 1.2.3.4 () Ports: 22/open/tcp//ssh///, 80/open/tcp//http///
        ports_part = ""
        if "Ports: " in line:
            ports_part = line.split("Ports: ", 1)[1]
        elif "\t" in line:
            ports_part = line.split("\t")[-1]
        for entry in ports_part.split(", "):
            fields = entry.strip().split("/")
            if len(fields) < 3:
                continue
            port_num, state, protocol = fields[0], fields[1], fields[2]
            if state != "open":
                continue
            service = fields[4] if len(fields) > 4 else ""
            version = fields[6] if len(fields) > 6 else ""
            ports.append(Node(
                type="port",
                value=f"{target}:{port_num}",
                label=f"{port_num}/{protocol}" + (f" {service}" if service else ""),
                data={
                    "port": int(port_num),
                    "protocol": protocol,
                    "service": service,
                    "version": version,
                    "state": "open",
                    "host": target,
                },
            ))

    if not ports:
        return [Node(type="note", value="no open ports in top 100", label="0 open ports")]
    return ports
