"""Extract the host/domain component of a URL."""

from urllib.parse import urlparse

from osint_engine.sdk import Node, transform


@transform(
    name="url.to_domain",
    display_name="URL → Domain",
    description="Parses a URL and emits its hostname as a domain node.",
    category="parsing",
    input_types=["url"],
    output_types=["domain"],
    author="builtin",
)
def run(node, api_keys):
    try:
        host = urlparse(node.value).hostname
    except Exception:
        return []
    if not host:
        return []
    return [Node(type="domain", value=host, label=host)]
