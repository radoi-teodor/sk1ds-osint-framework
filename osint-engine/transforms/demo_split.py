"""Offline demo transform — splits a string value by comma into multiple nodes."""

from osint_engine.sdk import Node, transform


@transform(
    name="demo.split",
    display_name="Split by comma (demo)",
    description="Splits the input value by comma and emits one 'note' node per piece. Offline.",
    category="demo",
    input_types=["*"],
    output_types=["note"],
    author="builtin",
)
def run(node, api_keys):
    pieces = [p.strip() for p in str(node.value).split(",") if p.strip()]
    return [Node(type="note", value=p, label=p) for p in pieces]
