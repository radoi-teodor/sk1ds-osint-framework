"""
Public SDK for defining transformations.

A transformation is a Python function decorated with @transform. It receives
a single Node and a dict of API keys, and returns a list of Nodes (and
optionally Edges) that become children of the input node in the graph.

Example:

    from osint_engine.sdk import transform, Node

    @transform(
        name="dns.resolve",
        display_name="DNS -> A records",
        description="Resolves a domain to IPv4 addresses",
        category="network",
        input_types=["domain"],
        output_types=["ipv4"],
        timeout=10,
    )
    def run(node, api_keys):
        import socket
        _, _, ips = socket.gethostbyname_ex(node.value)
        return [Node(type="ipv4", value=ip) for ip in ips]
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Callable


@dataclass
class Node:
    type: str
    value: str
    label: str | None = None
    data: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return {
            "type": self.type,
            "value": self.value,
            "label": self.label or self.value,
            "data": self.data or {},
        }


@dataclass
class Edge:
    source: str
    target: str
    label: str | None = None
    data: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return {
            "source": self.source,
            "target": self.target,
            "label": self.label,
            "data": self.data or {},
        }


@dataclass
class TransformSpec:
    name: str
    display_name: str
    description: str
    category: str
    input_types: list[str]
    output_types: list[str]
    required_api_keys: list[str]
    timeout: int
    author: str
    requires_slave: bool = False
    func: Callable | None = None
    source_file: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "display_name": self.display_name,
            "description": self.description,
            "category": self.category,
            "input_types": self.input_types,
            "output_types": self.output_types,
            "required_api_keys": self.required_api_keys,
            "requires_slave": self.requires_slave,
            "timeout": self.timeout,
            "author": self.author,
            "source_file": self.source_file,
        }


_REGISTRY: dict[str, TransformSpec] = {}


def transform(
    *,
    name: str,
    display_name: str,
    description: str = "",
    category: str = "general",
    input_types: list[str] | None = None,
    output_types: list[str] | None = None,
    required_api_keys: list[str] | None = None,
    requires_slave: bool = False,
    timeout: int = 30,
    author: str = "",
):
    """Decorator: register a function as a transformation.

    input_types may contain "*" to match any node type.
    If requires_slave is True, the transform function receives a third
    argument: a SlaveClient instance.
    """

    def decorator(func: Callable) -> Callable:
        spec = TransformSpec(
            name=name,
            display_name=display_name,
            description=description,
            category=category,
            input_types=list(input_types or ["*"]),
            output_types=list(output_types or []),
            required_api_keys=list(required_api_keys or []),
            requires_slave=requires_slave,
            timeout=timeout,
            author=author,
            func=func,
        )
        _REGISTRY[name] = spec
        return func

    return decorator


def get_registry() -> dict[str, TransformSpec]:
    return _REGISTRY


def clear_registry() -> None:
    _REGISTRY.clear()
