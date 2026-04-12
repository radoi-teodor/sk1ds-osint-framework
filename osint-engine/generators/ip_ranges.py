"""IP range generator.

Reads a file of CIDR ranges or individual IPs and expands them into a list.
Useful with nmap or other network scanning transforms.
"""

import ipaddress

from osint_engine.generator_sdk import GeneratorInputs, generator


@generator(
    name="ip_ranges",
    display_name="IP range expander",
    description="Reads a file or text with CIDR ranges (one per line) and expands to individual IPs. Max 10000 IPs. Use with nmap or ping sweep transforms.",
    category="network",
    input_types=["file", "text"],
    timeout=15,
    author="builtin",
)
def run(inputs: GeneratorInputs) -> str:
    raw = ""
    if inputs.files:
        raw = inputs.read_files()
    elif inputs.text:
        raw = inputs.text

    ips: list[str] = []
    for line in raw.splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        try:
            net = ipaddress.ip_network(line, strict=False)
            if net.num_addresses > 10000:
                ips.append(f"# skipped {line} (>{net.num_addresses} hosts)")
                continue
            for ip in net.hosts():
                ips.append(str(ip))
                if len(ips) > 10000:
                    ips.append("# truncated at 10000")
                    return "\n".join(ips)
        except ValueError:
            ips.append(line)
    return "\n".join(ips)
