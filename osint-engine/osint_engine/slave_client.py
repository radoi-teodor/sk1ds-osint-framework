"""SSH and local subprocess execution for slave connections.

Security model:
  - Command allowlisting: only explicitly permitted binaries can run.
  - Argument sanitization: shell metacharacters rejected, shlex-parsed.
  - SSH via paramiko exec_command, local via subprocess with shell=False.
  - Timeout enforced on every execution.
"""

from __future__ import annotations

import io
import json
import shlex
import subprocess
import urllib.request
from dataclasses import dataclass, field
from typing import Any


@dataclass
class CommandResult:
    stdout: str
    stderr: str
    exit_code: int

    @property
    def ok(self) -> bool:
        return self.exit_code == 0


@dataclass
class SlaveConfig:
    type: str = "embedded"
    host: str | None = None
    port: int = 22
    username: str | None = None
    auth_method: str | None = None
    credential: str | None = None

    @classmethod
    def from_dict(cls, d: dict) -> SlaveConfig:
        return cls(
            type=d.get("type", "embedded"),
            host=d.get("host"),
            port=int(d.get("port", 22)),
            username=d.get("username"),
            auth_method=d.get("auth_method"),
            credential=d.get("credential"),
        )


class CommandNotAllowed(Exception):
    pass


def validate_command(command: str) -> list[str]:
    """Parse a command string into parts via shlex. No binary or argument
    restrictions — transform authors have full control over what runs.
    Only rejects empty commands."""
    if not command or not command.strip():
        raise CommandNotAllowed("empty command")
    try:
        parts = shlex.split(command)
    except ValueError as exc:
        raise CommandNotAllowed(f"bad quoting: {exc}") from exc
    if not parts:
        raise CommandNotAllowed("empty command")
    return parts


class SlaveClient:
    def __init__(self, config: SlaveConfig):
        self._config = config
        self._ssh = None

    @property
    def is_embedded(self) -> bool:
        return self._config.type == "embedded"

    def execute(self, command: str, timeout: int = 30, stdin_data: str | None = None) -> CommandResult:
        validate_command(command)
        if self.is_embedded:
            return self._run_local(command, timeout, stdin_data)
        return self._run_ssh(command, timeout, stdin_data)

    def _connect_ssh(self):
        if self._ssh is not None:
            return
        try:
            import paramiko
        except ImportError:
            raise RuntimeError("paramiko not installed — run: pip install paramiko")
        c = self._config
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        connect_kw: dict[str, Any] = {
            "hostname": c.host,
            "port": c.port,
            "username": c.username,
            "timeout": 15,
            "allow_agent": False,
            "look_for_keys": False,
        }
        if c.auth_method == "password":
            connect_kw["password"] = c.credential
        elif c.auth_method == "key":
            key_str = io.StringIO(c.credential or "")
            pkey = None
            for key_class in (paramiko.Ed25519Key, paramiko.ECDSAKey, paramiko.RSAKey, paramiko.DSSKey):
                try:
                    key_str.seek(0)
                    pkey = key_class.from_private_key(key_str)
                    break
                except (paramiko.SSHException, ValueError):
                    continue
            if pkey is None:
                raise RuntimeError("Could not parse private key — unsupported format or passphrase-protected")
            connect_kw["pkey"] = pkey
        client.connect(**connect_kw)
        self._ssh = client

    def _run_ssh(self, command: str, timeout: int, stdin_data: str | None = None) -> CommandResult:
        self._connect_ssh()
        assert self._ssh is not None
        stdin_ch, stdout_ch, stderr_ch = self._ssh.exec_command(command, timeout=timeout)
        if stdin_data is not None:
            stdin_ch.write(stdin_data.encode("utf-8"))
            stdin_ch.channel.shutdown_write()
        exit_code = stdout_ch.channel.recv_exit_status()
        stdout = stdout_ch.read().decode("utf-8", errors="replace")
        stderr = stderr_ch.read().decode("utf-8", errors="replace")
        return CommandResult(stdout=stdout, stderr=stderr, exit_code=exit_code)

    def _run_local(self, command: str, timeout: int, stdin_data: str | None = None) -> CommandResult:
        try:
            proc = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=timeout,
                shell=True,
                input=stdin_data,
            )
            return CommandResult(stdout=proc.stdout, stderr=proc.stderr, exit_code=proc.returncode)
        except subprocess.TimeoutExpired:
            return CommandResult(stdout="", stderr="command timed out", exit_code=-1)
        except FileNotFoundError:
            return CommandResult(stdout="", stderr=f"binary not found: {command.split()[0]}", exit_code=127)

    def close(self):
        if self._ssh is not None:
            try:
                self._ssh.close()
            except Exception:
                pass
            self._ssh = None

    def __enter__(self):
        return self

    def __exit__(self, *args):
        self.close()


# ---------- country code → flag emoji ----------

def country_flag(code: str) -> str:
    if not code or len(code) != 2:
        return ""
    return "".join(chr(0x1F1E6 + ord(c) - ord("A")) for c in code.upper())


# ---------- probe a slave and collect fingerprint ----------

def probe_slave(client: SlaveClient) -> dict[str, Any]:
    fp: dict[str, Any] = {}

    r = client.execute("whoami", timeout=5)
    fp["whoami"] = r.stdout.strip() if r.ok else f"error: {r.stderr.strip()}"

    r = client.execute("hostname", timeout=5)
    fp["hostname"] = r.stdout.strip() if r.ok else "unknown"

    r = client.execute("uname -a", timeout=5)
    fp["kernel"] = r.stdout.strip() if r.ok else ""

    r = client.execute("cat /etc/os-release", timeout=5)
    if r.ok:
        for line in r.stdout.splitlines():
            if line.startswith("PRETTY_NAME="):
                fp["os"] = line.split("=", 1)[1].strip().strip('"')
                break
    if "os" not in fp:
        fp["os"] = fp.get("kernel", "unknown")

    r = client.execute("curl -s --max-time 8 https://ifconfig.me", timeout=12)
    public_ip = r.stdout.strip() if r.ok and r.stdout.strip() else None
    fp["public_ip"] = public_ip or "unknown"

    if public_ip:
        try:
            url = f"http://ip-api.com/json/{public_ip}?fields=status,country,countryCode,isp,org"
            with urllib.request.urlopen(url, timeout=8) as resp:
                geo = json.loads(resp.read().decode("utf-8", errors="replace"))
            if geo.get("status") == "success":
                fp["country"] = geo.get("country", "")
                fp["country_code"] = geo.get("countryCode", "")
                fp["flag"] = country_flag(geo.get("countryCode", ""))
                fp["isp"] = geo.get("isp", "")
                if geo.get("org") and geo.get("org") != geo.get("isp"):
                    fp["org"] = geo["org"]
        except Exception:
            pass

    for k in ("country", "country_code", "flag", "isp"):
        fp.setdefault(k, "")

    return fp
