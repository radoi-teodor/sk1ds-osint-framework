"""Fetch HTTP response headers for a domain or URL."""

import urllib.request

from osint_engine.sdk import Node, transform


_INTERESTING = {
    "server", "x-powered-by", "via", "x-generator", "x-aspnet-version",
    "x-amz-cf-id", "cf-ray", "x-cache", "x-cdn", "x-served-by",
}


@transform(
    name="http.headers",
    display_name="HTTP response headers",
    description="Does a HEAD request (fallback to GET) and emits each response header as a note. Interesting headers (server, x-powered-by, etc.) get highlighted.",
    category="web",
    input_types=["domain", "url"],
    output_types=["note"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    url = node.value if node.value.startswith(("http://", "https://")) else f"http://{node.value}"
    headers = None
    final_url = url
    for method in ("HEAD", "GET"):
        try:
            req = urllib.request.Request(url, method=method, headers={"User-Agent": "osint-engine/0.1"})
            with urllib.request.urlopen(req, timeout=10) as resp:
                headers = dict(resp.headers.items())
                final_url = resp.geturl()
            break
        except Exception:
            continue

    if headers is None:
        return [Node(type="note", value=f"unreachable: {url}", label="unreachable")]

    out = [Node(type="note", value=f"final_url: {final_url}", label=f"→ {final_url}")]
    for k, v in headers.items():
        label = f"{k}: {v}"
        if len(label) > 120:
            label = label[:117] + "..."
        interesting = k.lower() in _INTERESTING
        out.append(Node(
            type="note",
            value=f"{k}: {v}",
            label=("★ " if interesting else "") + label,
            data={"header": k, "value": v, "interesting": interesting},
        ))
    return out
