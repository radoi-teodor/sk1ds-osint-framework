"""Decompose a URL into scheme/host/port/path/query components."""

from urllib.parse import parse_qsl, urlparse

from osint_engine.sdk import Node, transform


@transform(
    name="url.parse",
    display_name="URL → components",
    description="Splits a URL into scheme, host, port, path, query params. Emits each as a separate note/domain/port.",
    category="parsing",
    input_types=["url"],
    output_types=["domain", "port", "note"],
    author="builtin",
)
def run(node, api_keys):
    try:
        p = urlparse(node.value)
    except Exception as exc:
        return [Node(type="note", value=f"parse error: {exc}", label="parse error")]

    out: list[Node] = []
    if p.scheme:
        out.append(Node(type="note", value=f"scheme: {p.scheme}", label=p.scheme))
    if p.hostname:
        out.append(Node(type="domain", value=p.hostname, label=p.hostname))
    if p.port:
        out.append(Node(type="port", value=str(p.port), label=f"port {p.port}"))
    if p.path and p.path != "/":
        out.append(Node(type="note", value=f"path: {p.path}", label=p.path))
    if p.query:
        for k, v in parse_qsl(p.query, keep_blank_values=True):
            out.append(Node(type="note", value=f"{k}={v}", label=f"?{k}={v[:40]}"))
    if p.fragment:
        out.append(Node(type="note", value=f"fragment: {p.fragment}", label=f"#{p.fragment}"))
    return out
