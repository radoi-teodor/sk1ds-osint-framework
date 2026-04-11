"""Fetch /favicon.ico and compute hashes that are useful for pivoting (Shodan-style)."""

import hashlib
import urllib.request

from osint_engine.sdk import Node, transform


@transform(
    name="http.favicon_hash",
    display_name="Favicon hash",
    description="Downloads /favicon.ico and emits MD5 + SHA256 hashes. Useful for pivoting on Shodan/Censys (http.favicon.hash queries).",
    category="web",
    input_types=["domain", "url"],
    output_types=["hash", "note"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    host = node.value
    if host.startswith(("http://", "https://")):
        from urllib.parse import urlparse
        host = urlparse(host).hostname or host
    url = f"http://{host}/favicon.ico"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            blob = resp.read(2_000_000)
    except Exception as exc:
        return [Node(type="note", value=f"no favicon: {exc}", label="no favicon")]

    if not blob:
        return [Node(type="note", value="empty favicon", label="empty favicon")]

    md5 = hashlib.md5(blob).hexdigest()
    sha256 = hashlib.sha256(blob).hexdigest()
    return [
        Node(type="hash", value=md5, label=f"md5:{md5[:12]}", data={"algo": "md5", "source": "favicon", "size": len(blob)}),
        Node(type="hash", value=sha256, label=f"sha256:{sha256[:12]}", data={"algo": "sha256", "source": "favicon", "size": len(blob)}),
    ]
