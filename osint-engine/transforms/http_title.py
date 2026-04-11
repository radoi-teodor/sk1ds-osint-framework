"""Fetch a URL (or http://<domain>) and extract the HTML <title>."""

import re
import urllib.request

from osint_engine.sdk import Node, transform


_TITLE_RE = re.compile(r"<title[^>]*>([^<]*)</title>", re.IGNORECASE)


@transform(
    name="http.title",
    display_name="HTTP page title",
    description="GETs the target and extracts the <title> tag (first 200 chars).",
    category="web",
    input_types=["domain", "url"],
    output_types=["title"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    url = node.value if node.value.startswith(("http://", "https://")) else f"http://{node.value}"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            body = resp.read(65536).decode("utf-8", errors="replace")
    except Exception:
        return []
    m = _TITLE_RE.search(body)
    if not m:
        return []
    title = m.group(1).strip()[:200]
    if not title:
        return []
    return [Node(type="title", value=title, label=title, data={"source_url": url})]
