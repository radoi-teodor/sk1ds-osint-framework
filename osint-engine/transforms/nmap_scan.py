"""Nmap scan transforms via slave SSH/local execution.

All transforms share _parse_greppable() for nmap -oG output and _safe_target()
for input validation. Each is a thin wrapper with different nmap flags.
"""

import re

from osint_engine.sdk import Node, transform

_SAFE_TARGET = re.compile(r"^[a-zA-Z0-9.\-:]+$")


def _safe_target(node) -> str | None:
    t = node.value.strip()
    if not _SAFE_TARGET.match(t):
        return None
    return t


def _target_from_port_node(node) -> tuple[str | None, int | None]:
    """Extract host + port from a port node (value = 'host:port')."""
    d = node.data if isinstance(node.data, dict) else {}
    host = d.get("host")
    port = d.get("port")
    if host and port:
        return str(host), int(port)
    if ":" in node.value:
        parts = node.value.rsplit(":", 1)
        try:
            return parts[0], int(parts[1])
        except (ValueError, IndexError):
            pass
    return None, None


def _parse_greppable(stdout: str, default_host: str = "") -> list[Node]:
    """Parse nmap greppable (-oG) output into port nodes."""
    ports = []
    for line in stdout.splitlines():
        if "/open/" not in line:
            continue

        host = default_host
        host_match = re.match(r"Host:\s+(\S+)", line)
        if host_match:
            host = host_match.group(1)

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
                value=f"{host}:{port_num}",
                label=f"{port_num}/{protocol}" + (f" {service}" if service else ""),
                data={
                    "port": int(port_num),
                    "protocol": protocol,
                    "service": service,
                    "version": version,
                    "state": "open",
                    "host": host,
                },
            ))
    return ports


def _run_nmap(slave, target: str, flags: str, timeout: int = 90) -> list[Node]:
    """Execute nmap with given flags, parse greppable output."""
    result = slave.execute(f"nmap {flags} -oG - {target}", timeout=timeout)
    if not result.ok:
        return [Node(
            type="note",
            value=f"nmap error (exit {result.exit_code}): {result.stderr[:400]}",
            label="nmap error",
        )]
    ports = _parse_greppable(result.stdout, target)
    if not ports:
        return [Node(type="note", value="no open ports found", label="0 open")]
    return ports


def _parse_script_output(stdout: str, host: str) -> list[Node]:
    """Parse verbose nmap script output into note nodes."""
    notes = []
    current_port = ""
    for line in stdout.splitlines():
        port_match = re.match(r"^(\d+/\w+)\s+open\s+(\S*)", line)
        if port_match:
            current_port = port_match.group(1)
            svc = port_match.group(2)
            if svc:
                notes.append(Node(
                    type="note",
                    value=f"{current_port} {svc}",
                    label=f"{current_port} {svc}",
                    data={"host": host, "port": current_port},
                ))
            continue
        line_s = line.strip()
        if line_s.startswith("|") or line_s.startswith("Service Info:"):
            text = line_s.lstrip("|_- ").strip()
            if text and len(text) > 2:
                prefix = f"{current_port}: " if current_port else ""
                notes.append(Node(
                    type="note",
                    value=f"{prefix}{text}",
                    label=f"{prefix}{text[:80]}",
                    data={"host": host, "port": current_port, "script_output": True},
                ))
    return notes


# ---------- port scan transforms ----------

@transform(
    name="nmap.ping",
    display_name="Nmap: host alive?",
    description="Checks if the host is up using ICMP, TCP SYN and ARP (-sn). Fast.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["note"],
    requires_slave=True,
    timeout=30,
    author="builtin",
)
def run_ping(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    result = slave.execute(f"nmap -sn {target}", timeout=20)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:200]}", label="nmap error")]
    up = False
    latency = ""
    for line in result.stdout.splitlines():
        if "Host is up" in line:
            up = True
            m = re.search(r"\(([\d.]+)s latency\)", line)
            if m:
                latency = f" ({m.group(1)}s)"
    status = f"UP{latency}" if up else "DOWN"
    return [Node(type="note", value=f"{target}: {status}", label=status, data={"host": target, "alive": up})]


@transform(
    name="nmap.quick",
    display_name="Nmap: quick 20 ports",
    description="Very fast scan — top 20 TCP ports with aggressive timing (-T5).",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=60,
    author="builtin",
)
def run_quick(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    return _run_nmap(slave, target, "-T5 --top-ports 20", timeout=30)


@transform(
    name="nmap.top100",
    display_name="Nmap: top 100 ports",
    description="Quick TCP scan of the top 100 ports.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=120,
    author="builtin",
)
def run_top100(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    return _run_nmap(slave, target, "-T4 --top-ports 100", timeout=90)


@transform(
    name="nmap.top1000",
    display_name="Nmap: top 1000 ports",
    description="Thorough TCP scan — top 1000 ports (nmap default).",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=300,
    author="builtin",
)
def run_top1000(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    return _run_nmap(slave, target, "-T4 --top-ports 1000", timeout=240)


@transform(
    name="nmap.all_tcp",
    display_name="Nmap: all 65535 TCP ports",
    description="Full TCP port scan (-p-). Slow but comprehensive.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=600,
    author="builtin",
)
def run_all_tcp(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    return _run_nmap(slave, target, "-T4 -p-", timeout=540)


@transform(
    name="nmap.udp_top50",
    display_name="Nmap: UDP top 50 ports",
    description="UDP scan of the top 50 ports (-sU). Usually requires root/admin on the slave.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["port", "note"],
    requires_slave=True,
    timeout=300,
    author="builtin",
)
def run_udp(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    return _run_nmap(slave, target, "-sU -T4 --top-ports 50", timeout=240)


# ---------- service / OS detection ----------

@transform(
    name="nmap.service",
    display_name="Nmap: service/version detection",
    description="Probes open ports for service and version (-sV). On a port node, scans just that port. On IP/domain, scans top 100.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain", "port"],
    output_types=["note", "port"],
    requires_slave=True,
    timeout=180,
    author="builtin",
)
def run_service(node, api_keys, slave):
    if node.type == "port":
        host, port = _target_from_port_node(node)
        if not host:
            return [Node(type="note", value="can't parse port node", label="bad port")]
        target, flags = host, f"-sV -T4 -p {port}"
    else:
        target = _safe_target(node)
        if not target:
            return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
        flags = "-sV -T4 --top-ports 100"
    result = slave.execute(f"nmap {flags} {target}", timeout=150)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:300]}", label="nmap error")]
    notes = _parse_script_output(result.stdout, target)
    return notes if notes else [Node(type="note", value="no services detected", label="no services")]


@transform(
    name="nmap.os",
    display_name="Nmap: OS fingerprint",
    description="Attempts to identify the remote OS (-O). Usually requires root/admin on the slave.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["note"],
    requires_slave=True,
    timeout=120,
    author="builtin",
)
def run_os(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    result = slave.execute(f"nmap -O -T4 --top-ports 20 {target}", timeout=90)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:300]}", label="nmap error")]
    out = []
    for line in result.stdout.splitlines():
        ls = line.strip()
        for prefix in ("OS details:", "Running:", "OS CPE:", "Aggressive OS guesses:", "Device type:"):
            if ls.startswith(prefix):
                out.append(Node(type="note", value=ls, label=ls[:80], data={"host": target, "scan": "os"}))
                break
    return out if out else [Node(type="note", value="OS not identified", label="OS unknown")]


# ---------- NSE script scans ----------

@transform(
    name="nmap.vuln",
    display_name="Nmap: vulnerability scan",
    description="Runs NSE vuln scripts (--script vuln) against top 100 ports. Can be slow.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain"],
    output_types=["note"],
    requires_slave=True,
    timeout=600,
    author="builtin",
)
def run_vuln(node, api_keys, slave):
    target = _safe_target(node)
    if not target:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]
    result = slave.execute(f"nmap --script vuln -T4 --top-ports 100 {target}", timeout=540)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:300]}", label="nmap error")]
    notes = _parse_script_output(result.stdout, target)
    return notes if notes else [Node(type="note", value="no vulnerabilities found", label="clean")]


@transform(
    name="nmap.ssl",
    display_name="Nmap: SSL/TLS cert + ciphers",
    description="Enumerates SSL certificate details and cipher suites. On a port node, scans that port. On IP/domain, scans 443.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain", "port"],
    output_types=["note", "certificate"],
    requires_slave=True,
    timeout=120,
    author="builtin",
)
def run_ssl(node, api_keys, slave):
    if node.type == "port":
        host, port = _target_from_port_node(node)
        if not host:
            return [Node(type="note", value="can't parse port node", label="bad port")]
    else:
        host = _safe_target(node)
        port = 443
    if not host:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]

    result = slave.execute(f"nmap --script ssl-cert,ssl-enum-ciphers -p {port} {host}", timeout=60)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:300]}", label="nmap error")]
    out = []
    cert = {}
    for line in result.stdout.splitlines():
        ls = line.strip().lstrip("|_- ").strip()
        if not ls:
            continue
        if "Subject:" in ls:
            cert["subject"] = ls
        elif "Issuer:" in ls:
            cert["issuer"] = ls
        elif "Not valid" in ls:
            cert["validity"] = ls
        if any(k in ls.lower() for k in ("subject:", "issuer:", "not valid", "commonname", "altnames", "sha", "tlsv", "sslv", "cipher")):
            out.append(Node(type="note", value=ls, label=ls[:80], data={"host": host, "port": port}))
    if cert:
        out.insert(0, Node(type="certificate", value=f"{host}:{port}", label=f"cert {host}:{port}", data={"host": host, "port": port, **cert}))
    return out if out else [Node(type="note", value="no SSL/TLS info", label="no TLS")]


@transform(
    name="nmap.http",
    display_name="Nmap: HTTP enumeration",
    description="HTTP title, server header, methods, robots.txt via NSE. On a port node, scans that port. On IP/domain, scans 80,443,8080,8443.",
    category="recon",
    input_types=["ipv4", "ipv6", "domain", "port"],
    output_types=["note", "title"],
    requires_slave=True,
    timeout=120,
    author="builtin",
)
def run_http(node, api_keys, slave):
    if node.type == "port":
        host, port = _target_from_port_node(node)
        if not host:
            return [Node(type="note", value="can't parse port node", label="bad port")]
        port_flag = f"-p {port}"
    else:
        host = _safe_target(node)
        port_flag = "-p 80,443,8080,8443"
    if not host:
        return [Node(type="note", value=f"bad target: {node.value}", label="bad target")]

    result = slave.execute(
        f"nmap --script http-title,http-server-header,http-methods,http-robots.txt -T4 {port_flag} {host}",
        timeout=90,
    )
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:300]}", label="nmap error")]
    out = []
    for line in result.stdout.splitlines():
        ls = line.strip().lstrip("|_- ").strip()
        if not ls:
            continue
        ll = ls.lower()
        if "http-title:" in ll:
            title = ls.split(":", 1)[1].strip() if ":" in ls else ls
            out.append(Node(type="title", value=title, label=title[:60], data={"host": host}))
        elif "http-server-header:" in ll or "server:" in ll:
            out.append(Node(type="note", value=ls, label=ls[:80], data={"host": host, "field": "server"}))
        elif "http-methods:" in ll or "allowed methods:" in ll:
            out.append(Node(type="note", value=ls, label=ls[:80], data={"host": host, "field": "methods"}))
        elif "http-robots" in ll or "disallow" in ll:
            out.append(Node(type="note", value=ls, label=ls[:80], data={"host": host, "field": "robots"}))
    return out if out else [Node(type="note", value="no HTTP info", label="no HTTP")]
