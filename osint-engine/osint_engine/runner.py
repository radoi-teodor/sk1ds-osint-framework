"""Execute a transformation with a timeout and normalize its output."""

from __future__ import annotations

import asyncio
import traceback
from concurrent.futures import ThreadPoolExecutor
from typing import Any

from osint_engine.sdk import Edge, Node, TransformSpec


_executor = ThreadPoolExecutor(max_workers=8, thread_name_prefix="transform")


def _normalize(result: Any) -> tuple[list[dict], list[dict]]:
    """Accept list[Node] | dict{nodes,edges} | None from a transform."""
    if result is None:
        return [], []

    nodes: list[dict] = []
    edges: list[dict] = []

    if isinstance(result, list):
        for item in result:
            if isinstance(item, Node):
                nodes.append(item.to_dict())
            elif isinstance(item, dict):
                nodes.append(item)
        return nodes, edges

    if isinstance(result, dict):
        for n in result.get("nodes", []) or []:
            nodes.append(n.to_dict() if isinstance(n, Node) else n)
        for e in result.get("edges", []) or []:
            edges.append(e.to_dict() if isinstance(e, Edge) else e)
        return nodes, edges

    if isinstance(result, Node):
        return [result.to_dict()], []

    raise TypeError(f"Transform must return list/dict/Node, got {type(result).__name__}")


def _invoke(spec: TransformSpec, node: Node, api_keys: dict, slave_config: dict | None):
    """Call the transform function with the right arity (2 or 3 args)."""
    from osint_engine.slave_client import SlaveClient, SlaveConfig

    if spec.requires_slave and slave_config:
        cfg = SlaveConfig.from_dict(slave_config)
        with SlaveClient(cfg) as client:
            return spec.func(node, api_keys, client)
    elif spec.requires_slave:
        return [Node(type="note", value="no slave configured for this transform", label="no slave")]
    else:
        return spec.func(node, api_keys)


async def run_transform(
    spec: TransformSpec,
    node: Node,
    api_keys: dict[str, str],
    slave_config: dict | None = None,
) -> dict[str, Any]:
    """Run a transformation asynchronously with a timeout."""
    if spec.func is None:
        return {"nodes": [], "edges": [], "error": "Transform has no callable"}

    loop = asyncio.get_running_loop()
    try:
        raw = await asyncio.wait_for(
            loop.run_in_executor(
                _executor, _invoke, spec, node, api_keys, slave_config
            ),
            timeout=spec.timeout,
        )
    except asyncio.TimeoutError:
        return {
            "nodes": [],
            "edges": [],
            "error": f"Transform '{spec.name}' timed out after {spec.timeout}s",
        }
    except Exception as exc:
        return {
            "nodes": [],
            "edges": [],
            "error": f"{type(exc).__name__}: {exc}",
            "traceback": traceback.format_exc(),
        }

    try:
        nodes, edges = _normalize(raw)
    except Exception as exc:
        return {"nodes": [], "edges": [], "error": f"Bad return value: {exc}"}

    return {"nodes": nodes, "edges": edges, "error": None}
