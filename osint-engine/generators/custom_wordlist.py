"""Custom wordlist from text input.

Takes a text input (one entry per line) and passes it through as-is.
"""

from osint_engine.generator_sdk import GeneratorInputs, generator


@generator(
    name="custom_wordlist",
    display_name="Custom wordlist (text)",
    description="Enter a custom wordlist as text (one entry per line). Useful for quick adhoc fuzzing without uploading a file.",
    category="wordlists",
    input_types=["text"],
    timeout=5,
    author="builtin",
)
def run(inputs: GeneratorInputs) -> str:
    return inputs.text or ""
