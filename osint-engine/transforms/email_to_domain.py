"""Extract the domain part of an email address."""

from osint_engine.sdk import Node, transform


@transform(
    name="email.to_domain",
    display_name="Email → Domain",
    description="Splits an email on '@' and emits the domain as a new node.",
    category="parsing",
    input_types=["email"],
    output_types=["domain"],
    author="builtin",
)
def run(node, api_keys):
    if "@" not in node.value:
        return []
    dom = node.value.split("@", 1)[1].strip().lower()
    if not dom:
        return []
    return [Node(type="domain", value=dom, label=dom)]
