"""Phone lookup via the c99.nl API. Demonstrates API-key usage."""

import json
import urllib.parse
import urllib.request

from osint_engine.sdk import Node, transform


@transform(
    name="c99.phone_lookup",
    display_name="C99 Phone Lookup",
    description="Calls https://api.c99.nl/phonelookup. Requires API key C99_API_KEY.",
    category="c99",
    input_types=["phone"],
    output_types=["note", "location"],
    required_api_keys=["C99_API_KEY"],
    timeout=15,
    author="builtin",
)
def run(node, api_keys):
    key = api_keys.get("C99_API_KEY")
    if not key:
        return [
            Node(
                type="note",
                value="missing API key C99_API_KEY",
                label="missing C99_API_KEY",
            )
        ]

    params = urllib.parse.urlencode({"key": key, "number": node.value, "json": ""})
    url = f"https://api.c99.nl/phonelookup?{params}"
    try:
        with urllib.request.urlopen(url, timeout=10) as resp:
            data = json.loads(resp.read().decode("utf-8", errors="replace"))
    except Exception as exc:
        return [Node(type="note", value=f"c99 error: {exc}", label="c99 error")]

    if not data.get("success", False):
        return [Node(type="note", value=data.get("error", "unknown error"), label="c99 error")]

    out: list[Node] = []
    if data.get("provider"):
        out.append(Node(type="note", value=f"Provider: {data['provider']}", label=str(data["provider"])))
    if data.get("country"):
        out.append(Node(type="location", value=str(data["country"]), label=str(data["country"])))
    if data.get("carrier"):
        out.append(Node(type="note", value=f"Carrier: {data['carrier']}", label=str(data["carrier"])))
    return out