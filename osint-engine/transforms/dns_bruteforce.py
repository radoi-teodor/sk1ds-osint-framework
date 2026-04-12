"""DNS subdomain bruteforce using a generator wordlist.

Resolves each subdomain from the generator output against the target domain.
"""

import socket

from osint_engine.sdk import Node, transform


@transform(
    name="dns.bruteforce",
    display_name="DNS subdomain bruteforce",
    description="Takes a list of subdomains from a generator (one per line) and resolves each. Outputs subdomains that resolve successfully.",
    category="network",
    input_types=["domain"],
    output_types=["domain", "ipv4", "note"],
    accepts_generator=True,
    required_generators=["seclists", "custom_wordlist", "subdomain_list"],
    timeout=300,
    author="builtin",
)
def run(node, api_keys, generator_output=None):
    base = node.value.strip().rstrip(".")
    if not generator_output or not generator_output.strip():
        return [Node(type="note", value="no wordlist — run with a generator", label="no wordlist")]

    lines = [l.strip() for l in generator_output.strip().splitlines() if l.strip() and not l.startswith("#")]

    found = []
    for entry in lines[:5000]:
        # Entry might be a full FQDN or just a prefix
        if "." in entry and entry.endswith(base):
            fqdn = entry
        else:
            fqdn = f"{entry}.{base}"
        try:
            ips = socket.gethostbyname_ex(fqdn)[2]
        except (socket.gaierror, socket.herror, UnicodeError):
            continue
        found.append(Node(
            type="domain", value=fqdn, label=fqdn,
            data={"ips": ips, "base_domain": base},
        ))
        for ip in ips:
            found.append(Node(type="ipv4", value=ip, label=ip, data={"resolved_from": fqdn}))

    return found if found else [Node(type="note", value="no subdomains resolved", label="0 found")]
