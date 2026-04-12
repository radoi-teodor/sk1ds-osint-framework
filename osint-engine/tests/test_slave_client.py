"""Tests for slave_client: allowlisting, embedded execution, probe."""

import pytest
from osint_engine.slave_client import (
    CommandNotAllowed,
    CommandResult,
    SlaveClient,
    SlaveConfig,
    country_flag,
    probe_slave,
    validate_command,
)


# ---------- command allowlist ----------

def test_basic_command():
    parts = validate_command("whoami")
    assert parts == ["whoami"]


def test_any_binary_allowed():
    parts = validate_command("rm -rf /tmp/x")
    assert parts[0] == "rm"


def test_python_allowed():
    parts = validate_command("python3 -c 'print(1+2)'")
    assert parts[0] == "python3"


def test_bash_allowed():
    parts = validate_command("bash -c 'echo test && whoami'")
    assert parts[0] == "bash"


def test_complex_args():
    parts = validate_command("nmap -T4 --top-ports 100 -oG - 1.2.3.4")
    assert parts[0] == "nmap"
    assert "1.2.3.4" in parts


def test_empty_rejected():
    with pytest.raises(CommandNotAllowed):
        validate_command("")


def test_blank_rejected():
    with pytest.raises(CommandNotAllowed):
        validate_command("   ")


# ---------- embedded client ----------

def test_embedded_whoami():
    cfg = SlaveConfig(type="embedded")
    with SlaveClient(cfg) as c:
        r = c.execute("whoami", timeout=5)
    assert r.ok
    assert len(r.stdout.strip()) > 0


def test_embedded_hostname():
    cfg = SlaveConfig(type="embedded")
    with SlaveClient(cfg) as c:
        r = c.execute("hostname", timeout=5)
    assert r.ok


def test_embedded_runs_anything():
    cfg = SlaveConfig(type="embedded")
    with SlaveClient(cfg) as c:
        r = c.execute("echo hello", timeout=5)
    assert r.ok


def test_embedded_is_embedded():
    cfg = SlaveConfig(type="embedded")
    c = SlaveClient(cfg)
    assert c.is_embedded


# ---------- country flag ----------

def test_country_flag_us():
    assert country_flag("US") == "\U0001F1FA\U0001F1F8"


def test_country_flag_ro():
    assert country_flag("RO") == "\U0001F1F7\U0001F1F4"


def test_country_flag_empty():
    assert country_flag("") == ""
    assert country_flag("X") == ""


# ---------- probe embedded ----------

def test_probe_embedded():
    cfg = SlaveConfig(type="embedded")
    with SlaveClient(cfg) as c:
        fp = probe_slave(c)
    assert "whoami" in fp
    assert "hostname" in fp
    assert fp["whoami"]
    assert fp["hostname"]
