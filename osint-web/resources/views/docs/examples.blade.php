@extends('docs.layout')
@section('title', 'Examples')
@section('content')
<h1>Examples</h1>
<p>Real-world patterns from the built-in transforms.</p>

<h2>Simple: offline string utility</h2>
<pre>from osint_engine.sdk import transform, Node
import hashlib

@transform(
    name="string.hashes",
    display_name="String → hashes",
    description="Emits MD5 / SHA1 / SHA256 of the input value.",
    category="crypto",
    input_types=["*"],
    output_types=["hash"],
)
def run(node, api_keys):
    data = str(node.value).encode("utf-8")
    return [
        Node(type="hash", value=hashlib.md5(data).hexdigest(),
             label=f"md5:{hashlib.md5(data).hexdigest()[:12]}",
             data={"algo": "md5"}),
        Node(type="hash", value=hashlib.sha256(data).hexdigest(),
             label=f"sha256:{hashlib.sha256(data).hexdigest()[:12]}",
             data={"algo": "sha256"}),
    ]</pre>

<h2>Network: DNS resolve (stdlib)</h2>
<pre>import socket
from osint_engine.sdk import transform, Node

@transform(
    name="dns.resolve",
    display_name="DNS → A records",
    category="network",
    input_types=["domain"],
    output_types=["ipv4"],
    timeout=10,
)
def run(node, api_keys):
    try:
        _, _, ips = socket.gethostbyname_ex(node.value)
    except socket.gaierror:
        return []
    return [Node(type="ipv4", value=ip) for ip in ips]</pre>

<h2>API key: Snusbase breach search</h2>
<pre>import json, urllib.request
from osint_engine.sdk import transform, Node

@transform(
    name="snusbase.email",
    display_name="Snusbase: email → records",
    category="snusbase",
    input_types=["email"],
    output_types=["email", "username", "password", "hash"],
    required_api_keys=["SNUSBASE_API_KEY"],
    timeout=30,
)
def run(node, api_keys):
    key = api_keys.get("SNUSBASE_API_KEY")
    if not key:
        return [Node(type="note", value="missing SNUSBASE_API_KEY")]

    body = json.dumps({"terms": [node.value], "types": ["email"]}).encode()
    req = urllib.request.Request(
        "https://api.snusbase.com/data/search",
        data=body, method="POST",
        headers={"Auth": key, "Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=25) as resp:
        data = json.loads(resp.read().decode())

    nodes = []
    for breach, records in data.get("results", {}).items():
        for rec in records:
            nodes.append(Node(
                type="email",
                value=rec.get("email", ""),
                label=f"{rec.get('email', '')} · {breach[:30]}",
                data={"breach": breach, "record": rec},
            ))
    return nodes</pre>

<h2>Slave: nmap port scan</h2>
<pre>import re
from osint_engine.sdk import transform, Node

@transform(
    name="nmap.top100",
    display_name="Nmap: top 100 ports",
    category="recon",
    input_types=["ipv4", "domain"],
    output_types=["port"],
    requires_slave=True,
    timeout=120,
)
def run(node, api_keys, slave):
    target = node.value.strip()
    # Validate target is safe to interpolate
    if not re.match(r"^[a-zA-Z0-9.\-:]+$", target):
        return [Node(type="note", value="bad target")]

    result = slave.execute(f"nmap -T4 --top-ports 100 -oG - {target}", timeout=90)
    if not result.ok:
        return [Node(type="note", value=f"nmap error: {result.stderr[:200]}")]

    ports = []
    for line in result.stdout.splitlines():
        if "/open/" not in line:
            continue
        # Parse greppable: 22/open/tcp//ssh///
        for entry in line.split("Ports: ")[-1].split(", "):
            f = entry.strip().split("/")
            if len(f) >= 3 and f[1] == "open":
                ports.append(Node(
                    type="port",
                    value=f"{target}:{f[0]}",
                    label=f"{f[0]}/{f[2]} {f[4] if len(f) > 4 else ''}",
                    data={"port": int(f[0]), "protocol": f[2], "host": target},
                ))
    return ports or [Node(type="note", value="no open ports")]</pre>

<h2>Extractor: read data from parent node</h2>
<pre>from osint_engine.sdk import transform, Node

@transform(
    name="snusbase.extract_hash",
    display_name="Extract hash from record",
    category="snusbase",
    input_types=["*"],
    output_types=["hash", "note"],
)
def run(node, api_keys):
    rec = (node.data or {}).get("record")
    if not rec:
        return [Node(type="note", value="no record data")]
    val = rec.get("hash")
    if not val:
        return [Node(type="note", value="no hash in record")]
    return [Node(
        type="hash",
        value=str(val),
        data={"record": rec, "breach": (node.data or {}).get("breach", "")},
    )]</pre>
<p>This pattern lets you chain: <code>Snusbase email search → email node (with full record) → extract hash → hash node → hash reverse → password</code>.</p>

<h2>Generator + transform: directory bruteforce</h2>
<p>Upload a wordlist to File Manager, then use it with a transform via a generator:</p>
<pre>from osint_engine.sdk import transform, Node

@transform(
    name="web.feroxbuster",
    requires_slave=True,
    accepts_generator=True,
    required_generators=["seclists", "custom_wordlist"],
    input_types=["domain", "url"],
    output_types=["url"],
    timeout=600,
)
def run(node, api_keys, slave, generator_output=None):
    if not generator_output:
        return [Node(type="note", value="no wordlist")]
    slave.execute(f"printf '%s' '{generator_output}' > /tmp/wl.txt")
    result = slave.execute(f"feroxbuster -u {node.value} -w /tmp/wl.txt -q")
    return parse_output(result.stdout)</pre>
<p>See the <a href="/docs/generators">Generators</a> page for the full SDK and built-in generators.</p>

<h2>Third-party libraries</h2>
<p>Add libraries to <code>osint-engine/pyproject.toml</code> under <code>[project.optional-dependencies] extras</code>, then <code>pip install -e .[extras]</code>. Pre-listed: <code>requests</code>, <code>dnspython</code>, <code>python-whois</code>, <code>beautifulsoup4</code>.</p>
<pre># pyproject.toml
[project.optional-dependencies]
extras = [
    "requests>=2.31",
    "shodan>=1.28",      # ← add your library
]</pre>
<p>Then in your transform: <code>import shodan</code> — works immediately after install.</p>
@endsection
