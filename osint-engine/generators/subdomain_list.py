"""Subdomain list generator.

Reads a file of subdomain prefixes and prepends them to a base domain
provided as text input. Useful for subdomain bruteforce transforms.
"""

from osint_engine.generator_sdk import GeneratorInputs, generator


@generator(
    name="subdomain_list",
    display_name="Subdomain prefix list",
    description="Reads a file of subdomain prefixes (www, mail, api, etc.) and combines them with a base domain from text input to produce FQDN list.",
    category="dns",
    input_types=["file", "text"],
    timeout=15,
    author="builtin",
)
def run(inputs: GeneratorInputs) -> str:
    domain = (inputs.text or "").strip()
    if not domain:
        return ""
    prefixes = []
    if inputs.files:
        for line in inputs.read_files().splitlines():
            line = line.strip()
            if line and not line.startswith("#"):
                prefixes.append(line)
    if not prefixes:
        return domain
    return "\n".join(f"{p}.{domain}" for p in prefixes[:10000])
