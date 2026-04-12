"""Execute a generator with a timeout and return its string output."""

from __future__ import annotations

import asyncio
import traceback
from concurrent.futures import ThreadPoolExecutor
from typing import Any

from osint_engine.generator_sdk import GeneratorInputs, GeneratorSpec


_executor = ThreadPoolExecutor(max_workers=4, thread_name_prefix="generator")


async def run_generator(
    spec: GeneratorSpec,
    inputs: GeneratorInputs,
) -> dict[str, Any]:
    if spec.func is None:
        return {"output": "", "error": "Generator has no callable"}

    loop = asyncio.get_running_loop()
    try:
        raw = await asyncio.wait_for(
            loop.run_in_executor(_executor, spec.func, inputs),
            timeout=spec.timeout,
        )
    except asyncio.TimeoutError:
        return {"output": "", "error": f"Generator '{spec.name}' timed out after {spec.timeout}s"}
    except Exception as exc:
        return {"output": "", "error": f"{type(exc).__name__}: {exc}", "traceback": traceback.format_exc()}

    if raw is None:
        return {"output": "", "error": None}
    if not isinstance(raw, str):
        raw = str(raw)
    return {"output": raw, "error": None}
