"""SecLists / wordlist file reader.

Reads one or more uploaded wordlist files and returns their content as a
newline-separated string. Commonly used with feroxbuster, dirb, ffuf, etc.
"""

from osint_engine.generator_sdk import GeneratorInputs, generator


@generator(
    name="seclists",
    display_name="SecLists / wordlist reader",
    description="Reads uploaded wordlist files (one entry per line) and returns the combined content. Use with directory bruteforce or fuzzing transforms.",
    category="wordlists",
    input_types=["file"],
    timeout=15,
    author="builtin",
)
def run(inputs: GeneratorInputs) -> str:
    if not inputs.files:
        return ""
    return inputs.read_files()
