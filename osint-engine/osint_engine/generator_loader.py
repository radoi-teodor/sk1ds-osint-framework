"""Dynamic discovery of generator modules from a directory."""

from __future__ import annotations

import importlib.util
import sys
import traceback
from pathlib import Path

from osint_engine.generator_sdk import GeneratorSpec, clear_generator_registry, get_generator_registry


GENERATOR_LOAD_ERRORS: list[dict] = []


def load_generators(generators_dir: Path) -> dict[str, GeneratorSpec]:
    clear_generator_registry()
    GENERATOR_LOAD_ERRORS.clear()

    if not generators_dir.exists():
        return get_generator_registry()

    for py_file in sorted(generators_dir.glob("*.py")):
        if py_file.name.startswith("_"):
            continue
        module_name = f"osint_generators_{py_file.stem}"
        sys.modules.pop(module_name, None)
        spec = importlib.util.spec_from_file_location(module_name, py_file)
        if spec is None or spec.loader is None:
            continue
        module = importlib.util.module_from_spec(spec)
        sys.modules[module_name] = module
        try:
            spec.loader.exec_module(module)
        except Exception as exc:
            GENERATOR_LOAD_ERRORS.append({
                "file": py_file.name,
                "error": f"{type(exc).__name__}: {exc}",
                "traceback": traceback.format_exc(),
            })
            sys.modules.pop(module_name, None)
            continue
        for gs in get_generator_registry().values():
            if gs.source_file is None and gs.func is not None:
                if getattr(gs.func, "__module__", None) == module_name:
                    gs.source_file = str(py_file)

    return get_generator_registry()
