"""Compute Shannon entropy — useful for detecting random tokens/keys/secrets."""

import math
from collections import Counter

from osint_engine.sdk import Node, transform


@transform(
    name="string.entropy",
    display_name="Shannon entropy",
    description="Computes the Shannon entropy (bits/char) of the value. Useful to detect random-looking strings like API tokens, JWTs, hashes.",
    category="crypto",
    input_types=["*"],
    output_types=["note"],
    author="builtin",
)
def run(node, api_keys):
    s = str(node.value)
    if not s:
        return [Node(type="note", value="empty", label="empty")]

    counts = Counter(s)
    n = len(s)
    entropy = -sum((c / n) * math.log2(c / n) for c in counts.values())

    if entropy >= 4.5:
        verdict = "HIGH (likely random token/hash/key)"
    elif entropy >= 3.5:
        verdict = "MEDIUM (mixed)"
    else:
        verdict = "LOW (plain text)"

    return [Node(
        type="note",
        value=f"entropy={entropy:.3f} bits/char — {verdict}",
        label=f"H={entropy:.2f} {verdict.split()[0]}",
        data={"entropy": round(entropy, 4), "length": n, "unique_chars": len(counts)},
    )]
