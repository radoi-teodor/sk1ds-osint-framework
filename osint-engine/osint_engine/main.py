"""FastAPI application — HTTP surface for the OSINT transformation engine."""

from __future__ import annotations

import re
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Any

from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field

from osint_engine.auth import verify_secret
from osint_engine.config import settings
from osint_engine.loader import LOAD_ERRORS, load_transforms
from osint_engine.runner import run_transform
from osint_engine.sdk import Node, get_registry
from osint_engine.generator_loader import GENERATOR_LOAD_ERRORS, load_generators
from osint_engine.generator_runner import run_generator as run_gen
from osint_engine.generator_sdk import GeneratorInputs, get_generator_registry


@asynccontextmanager
async def lifespan(_: FastAPI):
    load_transforms(settings.transforms_dir)
    load_generators(settings.generators_dir)
    yield


app = FastAPI(title="OSINT Engine", version="0.1.0", lifespan=lifespan)


# ---------- request models ----------

class NodePayload(BaseModel):
    type: str
    value: str
    label: str | None = None
    data: dict[str, Any] = Field(default_factory=dict)


class SlavePayload(BaseModel):
    type: str = "embedded"
    host: str | None = None
    port: int = 22
    username: str | None = None
    auth_method: str | None = None
    credential: str | None = None


class RunRequest(BaseModel):
    node: NodePayload
    api_keys: dict[str, str] = Field(default_factory=dict)
    slave: SlavePayload | None = None
    generator_output: str | None = None


class GeneratorRunRequest(BaseModel):
    files: list[str] = Field(default_factory=list)
    text: str | None = None


class TestSlaveRequest(BaseModel):
    slave: SlavePayload


class SourceUpdate(BaseModel):
    source: str


class CreateTransform(BaseModel):
    filename: str
    source: str


class ValidateRequest(BaseModel):
    source: str


# ---------- endpoints ----------

@app.get("/health")
async def health() -> dict[str, Any]:
    return {
        "status": "ok",
        "transforms": len(get_registry()),
        "generators": len(get_generator_registry()),
    }


@app.get("/transforms", dependencies=[Depends(verify_secret)])
async def list_transforms() -> dict[str, Any]:
    return {
        "transforms": [spec.to_dict() for spec in get_registry().values()],
        "load_errors": LOAD_ERRORS,
    }


@app.post("/transforms/{name}/run", dependencies=[Depends(verify_secret)])
async def run_transform_endpoint(name: str, req: RunRequest) -> dict[str, Any]:
    registry = get_registry()
    spec = registry.get(name)
    if spec is None:
        raise HTTPException(404, f"Unknown transform: {name}")

    input_ok = "*" in spec.input_types or req.node.type in spec.input_types
    if not input_ok:
        raise HTTPException(
            400,
            f"Transform '{name}' expects {spec.input_types}, got '{req.node.type}'",
        )

    node = Node(
        type=req.node.type,
        value=req.node.value,
        label=req.node.label,
        data=req.node.data or {},
    )
    slave_dict = req.slave.model_dump() if req.slave else None
    return await run_transform(spec, node, req.api_keys, slave_config=slave_dict, generator_output=req.generator_output)


@app.post("/slaves/test", dependencies=[Depends(verify_secret)])
async def test_slave_endpoint(req: TestSlaveRequest) -> dict[str, Any]:
    import asyncio
    from concurrent.futures import ThreadPoolExecutor
    from osint_engine.slave_client import SlaveClient, SlaveConfig, probe_slave

    cfg = SlaveConfig.from_dict(req.slave.model_dump())
    loop = asyncio.get_running_loop()
    _pool = ThreadPoolExecutor(max_workers=1, thread_name_prefix="slave-test")
    try:
        def _probe():
            with SlaveClient(cfg) as client:
                return probe_slave(client)
        fp = await asyncio.wait_for(loop.run_in_executor(_pool, _probe), timeout=30)
    except Exception as exc:
        return {"ok": False, "error": f"{type(exc).__name__}: {exc}", "fingerprint": None}
    return {"ok": True, "fingerprint": fp}


class RunScriptRequest(BaseModel):
    slave: SlavePayload
    script: str


@app.post("/slaves/run-script", dependencies=[Depends(verify_secret)])
async def run_script_on_slave(req: RunScriptRequest) -> dict[str, Any]:
    import asyncio
    from concurrent.futures import ThreadPoolExecutor
    from osint_engine.slave_client import SlaveClient, SlaveConfig

    cfg = SlaveConfig.from_dict(req.slave.model_dump())
    loop = asyncio.get_running_loop()
    _pool = ThreadPoolExecutor(max_workers=1, thread_name_prefix="slave-script")
    try:
        def _run():
            with SlaveClient(cfg) as client:
                script = req.script.replace("\r\n", "\n").replace("\r", "\n")
                result = client.execute("bash", timeout=300, stdin_data=script)
                return {"stdout": result.stdout, "stderr": result.stderr, "exit_code": result.exit_code}
        out = await asyncio.wait_for(loop.run_in_executor(_pool, _run), timeout=330)
    except Exception as exc:
        return {"ok": False, "error": f"{type(exc).__name__}: {exc}", "output": None}
    return {"ok": out["exit_code"] == 0, "output": out, "error": out["stderr"] if out["exit_code"] != 0 else None}


@app.post("/reload", dependencies=[Depends(verify_secret)])
async def reload_transforms() -> dict[str, Any]:
    load_transforms(settings.transforms_dir)
    return {
        "status": "ok",
        "transforms": len(get_registry()),
        "load_errors": LOAD_ERRORS,
    }


@app.get("/transforms/{name}/source", dependencies=[Depends(verify_secret)])
async def get_source(name: str) -> dict[str, Any]:
    spec = get_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Transform or source file not found")
    path = Path(spec.source_file)
    return {
        "name": name,
        "filename": path.name,
        "source": path.read_text(encoding="utf-8"),
    }


@app.put("/transforms/{name}/source", dependencies=[Depends(verify_secret)])
async def update_source(name: str, body: SourceUpdate) -> dict[str, Any]:
    spec = get_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Transform or source file not found")
    # Validate syntax before writing.
    try:
        compile(body.source, spec.source_file, "exec")
    except SyntaxError as e:
        raise HTTPException(400, f"SyntaxError line {e.lineno}: {e.msg}")
    Path(spec.source_file).write_text(body.source, encoding="utf-8")
    load_transforms(settings.transforms_dir)
    return {"status": "ok", "load_errors": LOAD_ERRORS}


_FILENAME_RE = re.compile(r"^[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.py$")


@app.post("/transforms", dependencies=[Depends(verify_secret)])
async def create_transform(body: CreateTransform) -> dict[str, Any]:
    if not _FILENAME_RE.match(body.filename):
        raise HTTPException(400, "Invalid filename (use [a-zA-Z0-9_-].py)")
    try:
        compile(body.source, body.filename, "exec")
    except SyntaxError as e:
        raise HTTPException(400, f"SyntaxError line {e.lineno}: {e.msg}")
    target = settings.transforms_dir / body.filename
    if target.exists():
        raise HTTPException(409, "File already exists")
    settings.transforms_dir.mkdir(parents=True, exist_ok=True)
    target.write_text(body.source, encoding="utf-8")
    load_transforms(settings.transforms_dir)
    return {"status": "ok", "filename": body.filename, "load_errors": LOAD_ERRORS}


@app.delete("/transforms/{name}", dependencies=[Depends(verify_secret)])
async def delete_transform(name: str) -> dict[str, Any]:
    spec = get_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Transform or source file not found")
    Path(spec.source_file).unlink(missing_ok=True)
    load_transforms(settings.transforms_dir)
    return {"status": "ok"}


@app.post("/validate", dependencies=[Depends(verify_secret)])
async def validate(body: ValidateRequest) -> dict[str, Any]:
    try:
        compile(body.source, "<validate>", "exec")
    except SyntaxError as e:
        return {"valid": False, "error": f"Line {e.lineno}: {e.msg}"}
    return {"valid": True}


# ========== generator endpoints ==========

@app.get("/generators", dependencies=[Depends(verify_secret)])
async def list_generators() -> dict[str, Any]:
    return {
        "generators": [spec.to_dict() for spec in get_generator_registry().values()],
        "load_errors": GENERATOR_LOAD_ERRORS,
    }


@app.post("/generators/{name}/run", dependencies=[Depends(verify_secret)])
async def run_generator_endpoint(name: str, req: GeneratorRunRequest) -> dict[str, Any]:
    registry = get_generator_registry()
    spec = registry.get(name)
    if spec is None:
        raise HTTPException(404, f"Unknown generator: {name}")
    inputs = GeneratorInputs(text=req.text, files=req.files or [])
    return await run_gen(spec, inputs)


@app.post("/generators/reload", dependencies=[Depends(verify_secret)])
async def reload_generators() -> dict[str, Any]:
    load_generators(settings.generators_dir)
    return {"status": "ok", "generators": len(get_generator_registry()), "load_errors": GENERATOR_LOAD_ERRORS}


@app.get("/generators/{name}/source", dependencies=[Depends(verify_secret)])
async def get_generator_source(name: str) -> dict[str, Any]:
    spec = get_generator_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Generator or source file not found")
    path = Path(spec.source_file)
    return {"name": name, "filename": path.name, "source": path.read_text(encoding="utf-8")}


@app.put("/generators/{name}/source", dependencies=[Depends(verify_secret)])
async def update_generator_source(name: str, body: SourceUpdate) -> dict[str, Any]:
    spec = get_generator_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Generator or source file not found")
    try:
        compile(body.source, spec.source_file, "exec")
    except SyntaxError as e:
        raise HTTPException(400, f"SyntaxError line {e.lineno}: {e.msg}")
    Path(spec.source_file).write_text(body.source, encoding="utf-8")
    load_generators(settings.generators_dir)
    return {"status": "ok", "load_errors": GENERATOR_LOAD_ERRORS}


@app.post("/generators", dependencies=[Depends(verify_secret)])
async def create_generator_file(body: CreateTransform) -> dict[str, Any]:
    if not _FILENAME_RE.match(body.filename):
        raise HTTPException(400, "Invalid filename")
    try:
        compile(body.source, body.filename, "exec")
    except SyntaxError as e:
        raise HTTPException(400, f"SyntaxError line {e.lineno}: {e.msg}")
    target = settings.generators_dir / body.filename
    if target.exists():
        raise HTTPException(409, "File already exists")
    settings.generators_dir.mkdir(parents=True, exist_ok=True)
    target.write_text(body.source, encoding="utf-8")
    load_generators(settings.generators_dir)
    return {"status": "ok", "filename": body.filename}


@app.delete("/generators/{name}", dependencies=[Depends(verify_secret)])
async def delete_generator_file(name: str) -> dict[str, Any]:
    spec = get_generator_registry().get(name)
    if spec is None or not spec.source_file:
        raise HTTPException(404, "Generator or source file not found")
    Path(spec.source_file).unlink(missing_ok=True)
    load_generators(settings.generators_dir)
    return {"status": "ok"}


def main() -> None:
    import uvicorn

    uvicorn.run(
        "osint_engine.main:app",
        host=settings.host,
        port=settings.port,
        reload=False,
    )


if __name__ == "__main__":
    main()
