"""Tests for generator SDK, loader, and built-in generators."""

from pathlib import Path

from osint_engine.generator_loader import load_generators
from osint_engine.generator_runner import run_generator
from osint_engine.generator_sdk import (
    GeneratorInputs,
    clear_generator_registry,
    get_generator_registry,
    generator,
)
import asyncio

GENERATORS_DIR = Path(__file__).parent.parent / "generators"


def test_generator_decorator_registers():
    clear_generator_registry()

    @generator(name="test.gen", display_name="Test", input_types=["text"])
    def run(inputs):
        return inputs.text or ""

    reg = get_generator_registry()
    assert "test.gen" in reg
    assert reg["test.gen"].input_types == ["text"]


def test_loader_discovers_builtins():
    clear_generator_registry()
    reg = load_generators(GENERATORS_DIR)
    assert "seclists" in reg
    assert "custom_wordlist" in reg
    assert "ip_ranges" in reg
    assert "subdomain_list" in reg


def test_seclists_reads_file(tmp_path):
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["seclists"]
    f = tmp_path / "wordlist.txt"
    f.write_text("admin\nlogin\napi\n")
    result = spec.func(GeneratorInputs(files=[str(f)]))
    assert "admin" in result
    assert "login" in result
    assert "api" in result


def test_custom_wordlist_from_text():
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["custom_wordlist"]
    result = spec.func(GeneratorInputs(text="foo\nbar\nbaz"))
    assert result == "foo\nbar\nbaz"


def test_ip_ranges_expands_cidr():
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["ip_ranges"]
    result = spec.func(GeneratorInputs(text="10.0.0.0/30"))
    lines = result.strip().splitlines()
    assert "10.0.0.1" in lines
    assert "10.0.0.2" in lines


def test_subdomain_list_combines():
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["subdomain_list"]
    result = spec.func(GeneratorInputs(text="example.com", files=[]))
    assert result == "example.com"


def test_subdomain_list_with_prefixes(tmp_path):
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["subdomain_list"]
    f = tmp_path / "subs.txt"
    f.write_text("www\napi\nmail\n")
    result = spec.func(GeneratorInputs(text="example.com", files=[str(f)]))
    assert "www.example.com" in result
    assert "api.example.com" in result


def test_runner_returns_string():
    clear_generator_registry()
    load_generators(GENERATORS_DIR)
    spec = get_generator_registry()["custom_wordlist"]
    result = asyncio.run(run_generator(spec, GeneratorInputs(text="hello")))
    assert result["error"] is None
    assert result["output"] == "hello"


def test_runner_handles_error():
    clear_generator_registry()

    @generator(name="test.bad", display_name="Bad")
    def run(inputs):
        raise ValueError("boom")

    spec = get_generator_registry()["test.bad"]
    result = asyncio.run(run_generator(spec, GeneratorInputs()))
    assert "boom" in result["error"]
