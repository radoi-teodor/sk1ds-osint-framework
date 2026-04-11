"""Dynamic discovery of transformation modules from a directory."""

from __future__ import annotations

import importlib.util
import sys
import traceback
from pathlib import Path

from osint_engine.sdk import TransformSpec, clear_registry, get_registry


LOAD_ERRORS: list[dict] = []


def _module_name_for(path: Path) -> str:
    return f"osint_transforms_{path.stem}"


def load_transforms(transforms_dir: Path) -> dict[str, TransformSpec]:
    """Scan transforms_dir for *.py files and (re-)register them."""
    clear_registry()
    LOAD_ERRORS.clear()

    if not transforms_dir.exists():
        return get_registry()

    for py_file in sorted(transforms_dir.glob("*.py")):
        if py_file.name.startswith("_"):
            continue
        module_name = _module_name_for(py_file)

        # Drop any previously-imported version so edits are reflected.
        sys.modules.pop(module_name, None)

        spec = importlib.util.spec_from_file_location(module_name, py_file)
        if spec is None or spec.loader is None:
            continue
        module = importlib.util.module_from_spec(spec)
        sys.modules[module_name] = module
        try:
            spec.loader.exec_module(module)
        except Exception as exc:
            LOAD_ERRORS.append(
                {
                    "file": py_file.name,
                    "error": f"{type(exc).__name__}: {exc}",
                    "traceback": traceback.format_exc(),
                }
            )
            sys.modules.pop(module_name, None)
            continue

        # Attach source_file path to any specs that were freshly registered
        # from this module.
        for ts in get_registry().values():
            if ts.source_file is None and ts.func is not None:
                if getattr(ts.func, "__module__", None) == module_name:
                    ts.source_file = str(py_file)

    return get_registry()


def reload_single_file(transforms_dir: Path, filename: str) -> dict[str, TransformSpec]:
    """Convenience: re-run full load (full reload is cheap for our sizes)."""
    return load_transforms(transforms_dir)
