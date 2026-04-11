"""Extract the registrable root domain (best effort, no Public Suffix List)."""

from osint_engine.sdk import Node, transform


# A small whitelist of common 2-label eTLDs so "foo.co.uk" -> "foo.co.uk" instead of "co.uk".
_TWO_LABEL = {
    "co.uk", "org.uk", "ac.uk", "gov.uk", "me.uk", "net.uk",
    "com.au", "net.au", "org.au", "edu.au", "gov.au",
    "co.jp", "ne.jp", "ac.jp", "or.jp",
    "com.br", "com.mx", "com.ar", "com.tr", "com.sg",
    "co.in", "co.il", "co.kr", "co.nz", "co.za",
}


@transform(
    name="domain.tld",
    display_name="Domain → root domain + TLD",
    description="Extracts the TLD and registrable root domain (e.g. api.foo.co.uk → foo.co.uk). Uses a small heuristic for common 2-label eTLDs.",
    category="parsing",
    input_types=["domain"],
    output_types=["domain", "note"],
    author="builtin",
)
def run(node, api_keys):
    d = str(node.value).lower().strip(".")
    parts = d.split(".")
    if len(parts) < 2:
        return [Node(type="note", value="no TLD", label="no TLD")]

    # Check if last two labels form a known 2-label eTLD.
    last_two = ".".join(parts[-2:])
    if last_two in _TWO_LABEL and len(parts) >= 3:
        root = ".".join(parts[-3:])
        tld = last_two
    else:
        root = ".".join(parts[-2:])
        tld = parts[-1]

    out = [
        Node(type="note", value=f"tld: {tld}", label=f"tld:{tld}"),
    ]
    if root != d:
        out.append(Node(type="domain", value=root, label=f"root:{root}"))
    return out
