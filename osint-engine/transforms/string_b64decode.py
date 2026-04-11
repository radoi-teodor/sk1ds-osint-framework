"""Attempt to base64-decode a string and emit the result if it's printable."""

import base64

from osint_engine.sdk import Node, transform


@transform(
    name="string.b64decode",
    display_name="Base64 decode",
    description="Tries to base64-decode the value. Emits a note with the plaintext if decoding succeeds and the result looks printable.",
    category="crypto",
    input_types=["*"],
    output_types=["note"],
    author="builtin",
)
def run(node, api_keys):
    raw = str(node.value).strip()
    # Add padding if missing
    padding = "=" * (-len(raw) % 4)
    try:
        decoded = base64.b64decode(raw + padding, validate=False)
    except Exception as exc:
        return [Node(type="note", value=f"not base64: {exc}", label="not b64")]

    try:
        text = decoded.decode("utf-8")
        printable = sum(1 for c in text if c.isprintable() or c in "\r\n\t") / max(1, len(text))
        if printable > 0.85:
            return [Node(type="note", value=text[:500], label=f"b64→ {text[:40]}")]
    except UnicodeDecodeError:
        pass

    return [Node(
        type="note",
        value=f"{len(decoded)} bytes of binary",
        label=f"b64→ {decoded[:16].hex()}...",
        data={"length": len(decoded), "hex": decoded[:64].hex()},
    )]
