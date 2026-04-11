from pathlib import Path

from osint_engine.loader import LOAD_ERRORS, load_transforms
from osint_engine.sdk import clear_registry

TRANSFORMS_DIR = Path(__file__).parent.parent / "transforms"


def test_loader_discovers_builtins():
    clear_registry()
    reg = load_transforms(TRANSFORMS_DIR)
    assert "demo.echo" in reg
    assert "dns.resolve" in reg
    assert "email.to_domain" in reg
    assert "email.gravatar" in reg
    assert "http.title" in reg
    assert not LOAD_ERRORS


def test_loader_attaches_source_file():
    clear_registry()
    reg = load_transforms(TRANSFORMS_DIR)
    assert reg["demo.echo"].source_file is not None
    assert reg["demo.echo"].source_file.endswith("demo_echo.py")


def test_loader_reload_clears_old_entries(tmp_path):
    clear_registry()
    load_transforms(TRANSFORMS_DIR)
    before = set(load_transforms(TRANSFORMS_DIR).keys())
    after = set(load_transforms(TRANSFORMS_DIR).keys())
    assert before == after


def test_loader_handles_broken_file(tmp_path):
    clear_registry()
    (tmp_path / "good.py").write_text(
        "from osint_engine.sdk import transform\n"
        "@transform(name='tmp.good', display_name='Good', input_types=['*'])\n"
        "def run(node, api_keys):\n"
        "    return []\n"
    )
    (tmp_path / "broken.py").write_text("this is not python !!!\n")
    reg = load_transforms(tmp_path)
    assert "tmp.good" in reg
    assert any(e["file"] == "broken.py" for e in LOAD_ERRORS)
