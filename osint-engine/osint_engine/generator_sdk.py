"""
SDK for defining generators.

A generator is a Python function decorated with @generator. It receives
a GeneratorInputs object (optional text + file paths) and returns a string
that transforms can consume as input.

Example:

    from osint_engine.generator_sdk import generator, GeneratorInputs

    @generator(
        name="seclists",
        display_name="SecLists file reader",
        description="Reads an uploaded wordlist and returns its content",
        category="wordlists",
        input_types=["file"],
    )
    def run(inputs: GeneratorInputs) -> str:
        return inputs.read_files()
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Callable


@dataclass
class GeneratorInputs:
    text: str | None = None
    files: list[str] = field(default_factory=list)

    def read_files(self, encoding: str = "utf-8") -> str:
        """Read and concatenate all input files."""
        parts = []
        for fpath in self.files:
            with open(fpath, "r", encoding=encoding, errors="replace") as f:
                parts.append(f.read())
        return "\n".join(parts).strip()

    def read_file(self, index: int = 0, encoding: str = "utf-8") -> str:
        """Read a single file by index."""
        if index >= len(self.files):
            return ""
        with open(self.files[index], "r", encoding=encoding, errors="replace") as f:
            return f.read()


@dataclass
class GeneratorSpec:
    name: str
    display_name: str
    description: str
    category: str
    input_types: list[str]
    timeout: int
    author: str
    func: Callable | None = None
    source_file: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "display_name": self.display_name,
            "description": self.description,
            "category": self.category,
            "input_types": self.input_types,
            "timeout": self.timeout,
            "author": self.author,
            "source_file": self.source_file,
        }


_GENERATOR_REGISTRY: dict[str, GeneratorSpec] = {}


def generator(
    *,
    name: str,
    display_name: str,
    description: str = "",
    category: str = "general",
    input_types: list[str] | None = None,
    timeout: int = 30,
    author: str = "",
):
    """Decorator: register a function as a generator.

    input_types can be ["file"], ["text"], or ["file", "text"].
    """

    def decorator(func: Callable) -> Callable:
        spec = GeneratorSpec(
            name=name,
            display_name=display_name,
            description=description,
            category=category,
            input_types=list(input_types or ["text"]),
            timeout=timeout,
            author=author,
            func=func,
        )
        _GENERATOR_REGISTRY[name] = spec
        return func

    return decorator


def get_generator_registry() -> dict[str, GeneratorSpec]:
    return _GENERATOR_REGISTRY


def clear_generator_registry() -> None:
    _GENERATOR_REGISTRY.clear()
