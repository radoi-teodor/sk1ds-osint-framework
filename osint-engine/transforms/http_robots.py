"""Fetch robots.txt and extract Disallow paths + any sitemap URLs."""

import urllib.request

from osint_engine.sdk import Node, transform


@transform(
    name="http.robots",
    display_name="Fetch robots.txt",
    description="Downloads /robots.txt and emits each Disallow rule as a note and each Sitemap as a url node.",
    category="web",
    input_types=["domain", "url"],
    output_types=["note", "url"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    host = node.value
    if host.startswith(("http://", "https://")):
        from urllib.parse import urlparse
        host = urlparse(host).hostname or host
    url = f"http://{host}/robots.txt"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            body = resp.read(131072).decode("utf-8", errors="replace")
    except Exception as exc:
        return [Node(type="note", value=f"no robots.txt: {exc}", label="no robots.txt")]

    out = []
    for raw in body.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        low = line.lower()
        if low.startswith("disallow:"):
            path = line.split(":", 1)[1].strip()
            if path:
                out.append(Node(type="note", value=f"Disallow: {path}", label=f"Disallow {path}"))
        elif low.startswith("allow:"):
            path = line.split(":", 1)[1].strip()
            if path:
                out.append(Node(type="note", value=f"Allow: {path}", label=f"Allow {path}"))
        elif low.startswith("sitemap:"):
            sm = line.split(":", 1)[1].strip()
            if sm:
                out.append(Node(type="url", value=sm, label=f"sitemap: {sm}"))
        elif low.startswith("user-agent:"):
            ua = line.split(":", 1)[1].strip()
            if ua and ua != "*":
                out.append(Node(type="note", value=f"User-agent: {ua}", label=f"UA {ua}"))
    if not out:
        out.append(Node(type="note", value="robots.txt empty or unstructured", label="empty robots"))
    return out[:100]
