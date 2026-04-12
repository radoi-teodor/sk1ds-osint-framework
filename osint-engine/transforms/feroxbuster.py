"""Directory bruteforce using feroxbuster or a simple curl-based fallback.

Requires a slave + a generator that provides a wordlist string.
"""

import re

from osint_engine.sdk import Node, transform

_SAFE_TARGET = re.compile(r"^[a-zA-Z0-9.\-:/]+$")


@transform(
    name="web.feroxbuster",
    display_name="Feroxbuster: directory bruteforce",
    description="Runs feroxbuster (or curl fallback) against a URL/domain using a wordlist from a generator. Requires a slave and a wordlist generator.",
    category="recon",
    input_types=["domain", "url"],
    output_types=["url", "note"],
    requires_slave=True,
    accepts_generator=True,
    required_generators=["seclists", "custom_wordlist"],
    timeout=3600,
    author="builtin",
)
def run(node, api_keys, slave, generator_output=None):
    target = node.value.strip()
    if not target.startswith(("http://", "https://")):
        target = f"http://{target}"
    if not _SAFE_TARGET.match(target):
        return [Node(type="note", value=f"bad target: {target}", label="bad target")]
    if not generator_output or not generator_output.strip():
        return [Node(type="note", value="no wordlist provided — run with a generator", label="no wordlist")]

    # Write wordlist to temp file via stdin (avoids shell arg length limits)
    lines = [l.strip() for l in generator_output.strip().splitlines() if l.strip() and not l.startswith("#")]
    wordlist = "\n".join(lines)
    slave.execute("cat > /tmp/osint_wordlist.txt", timeout=15, stdin_data=wordlist)

    # Try feroxbuster first; fall back to curl-based probing
    check = slave.execute("which feroxbuster", timeout=5)
    if check.ok:
        result = slave.execute(
            f"feroxbuster -u {target} -w /tmp/osint_wordlist.txt -q --no-state -k --auto-tune",
            timeout=3500,
        )
        return _parse_ferox(result.stdout, target)
    else:
        return _curl_probe(slave, target, lines)


def _parse_ferox(stdout: str, target: str) -> list[Node]:
    out = []
    for line in stdout.splitlines():
        parts = line.split()
        if len(parts) < 2:
            continue
        code = parts[0]
        if not code.isdigit():
            continue
        url = parts[-1] if parts[-1].startswith("http") else parts[1]
        status = int(code)
        if status in (404, 400, 503):
            continue
        out.append(Node(
            type="url", value=url,
            label=f"[{status}] {url}",
            data={"status_code": status, "source": "feroxbuster", "target": target},
        ))
    return out if out else [Node(type="note", value="no results from feroxbuster", label="0 hits")]


def _curl_probe(slave, target: str, paths: list[str]) -> list[Node]:
    """Fallback: simple curl probing when feroxbuster is not installed."""
    out = []
    for path in paths[:200]:
        url = f"{target.rstrip('/')}/{path.lstrip('/')}"
        result = slave.execute(f"curl -s -o /dev/null -w '%{{http_code}}' -k -m 5 {url}", timeout=8)
        if not result.ok:
            continue
        code = result.stdout.strip()
        if code.isdigit() and int(code) not in (404, 000, 503):
            out.append(Node(
                type="url", value=url,
                label=f"[{code}] {url}",
                data={"status_code": int(code), "source": "curl_probe", "target": target},
            ))
    return out if out else [Node(type="note", value="no accessible paths found", label="0 hits")]
