"""Offline demo transform — echoes the input as a note node.

Useful as a smoke-test because it has no network dependency.
"""

from osint_engine.sdk import Node, transform


@transform(
    name="demo.echo",
    display_name="Echo (demo)",
    description="Returns a 'note' node whose value is 'echo:<input>'. No network. Good for offline testing.",
    category="demo",
    input_types=["*"],
    output_types=["note"],
    author="builtin",
)
def run(node, api_keys):
    return [
        Node(
            type="note",
            value=f"echo:{node.value}",
            label=f"echo:{node.value}",
            data={"source_type": node.type},
        )
    ]
