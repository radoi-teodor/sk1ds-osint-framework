"""Compute the Gravatar URL for an email address."""

import hashlib

from osint_engine.sdk import Node, transform


@transform(
    name="email.gravatar",
    display_name="Email → Gravatar",
    description="Builds the Gravatar avatar URL from the MD5 of the lowercased email.",
    category="parsing",
    input_types=["email"],
    output_types=["url"],
    author="builtin",
)
def run(node, api_keys):
    digest = hashlib.md5(node.value.strip().lower().encode("utf-8")).hexdigest()
    url = f"https://www.gravatar.com/avatar/{digest}?d=identicon&s=256"
    return [Node(type="url", value=url, label=f"gravatar:{digest[:8]}", data={"email": node.value})]
