"""Scan a URL for exposed secrets, credentials, API keys, and tokens.

Fetches the page content and runs regex patterns against it. Works on
URLs discovered by feroxbuster, or any URL/domain node.
"""

import re
import urllib.request

from osint_engine.sdk import Node, transform

# Each pattern: (name, regex, severity)
_PATTERNS: list[tuple[str, re.Pattern, str]] = [
    # AWS
    ("AWS Access Key", re.compile(r"AKIA[0-9A-Z]{16}"), "HIGH"),
    ("AWS Secret Key", re.compile(r"(?i)aws(.{0,20})?['\"][0-9a-zA-Z/+]{40}['\"]"), "CRITICAL"),
    ("AWS MWS Key", re.compile(r"amzn\\.mws\\.[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"), "HIGH"),

    # Google
    ("Google API Key", re.compile(r"AIza[0-9A-Za-z\-_]{35}"), "HIGH"),
    ("Google OAuth", re.compile(r"[0-9]+-[0-9A-Za-z_]{32}\\.apps\\.googleusercontent\\.com"), "MEDIUM"),
    ("Google Cloud Key", re.compile(r"(?i)google(.{0,20})?['\"][AIza][0-9A-Za-z\\-_]{35}['\"]"), "HIGH"),

    # GitHub / GitLab
    ("GitHub Token", re.compile(r"gh[pousr]_[A-Za-z0-9_]{36,255}"), "CRITICAL"),
    ("GitHub OAuth", re.compile(r"gho_[A-Za-z0-9_]{36,255}"), "CRITICAL"),
    ("GitLab Token", re.compile(r"glpat-[A-Za-z0-9\-_]{20,}"), "CRITICAL"),

    # Slack
    ("Slack Token", re.compile(r"xox[baprs]-[0-9]{10,13}-[0-9a-zA-Z]{10,48}"), "HIGH"),
    ("Slack Webhook", re.compile(r"https://hooks\\.slack\\.com/services/T[A-Z0-9]{8,}/B[A-Z0-9]{8,}/[a-zA-Z0-9]{20,}"), "MEDIUM"),

    # Stripe
    ("Stripe Secret Key", re.compile(r"sk_live_[0-9a-zA-Z]{24,}"), "CRITICAL"),
    ("Stripe Publishable", re.compile(r"pk_live_[0-9a-zA-Z]{24,}"), "LOW"),

    # Twilio
    ("Twilio API Key", re.compile(r"SK[0-9a-fA-F]{32}"), "HIGH"),

    # Mailgun / Sendgrid
    ("Mailgun API Key", re.compile(r"key-[0-9a-zA-Z]{32}"), "HIGH"),
    ("Sendgrid API Key", re.compile(r"SG\\.[A-Za-z0-9\\-_]{22}\\.[A-Za-z0-9\\-_]{43}"), "HIGH"),

    # Private keys
    ("RSA Private Key", re.compile(r"-----BEGIN RSA PRIVATE KEY-----"), "CRITICAL"),
    ("EC Private Key", re.compile(r"-----BEGIN EC PRIVATE KEY-----"), "CRITICAL"),
    ("DSA Private Key", re.compile(r"-----BEGIN DSA PRIVATE KEY-----"), "CRITICAL"),
    ("PGP Private Key", re.compile(r"-----BEGIN PGP PRIVATE KEY BLOCK-----"), "CRITICAL"),
    ("SSH Private Key", re.compile(r"-----BEGIN OPENSSH PRIVATE KEY-----"), "CRITICAL"),

    # JWT
    ("JWT Token", re.compile(r"eyJ[A-Za-z0-9_-]{10,}\\.eyJ[A-Za-z0-9_-]{10,}\\.[A-Za-z0-9_-]{10,}"), "MEDIUM"),

    # Generic secrets in common config patterns
    ("Generic Secret Assignment", re.compile(r"""(?i)(?:password|passwd|pwd|secret|token|api_key|apikey|api-key|access_key|auth_token|credentials)[\s]*[=:]+[\s]*['\"][^\s'\"]{8,}['\"]"""), "MEDIUM"),
    ("Database URL", re.compile(r"(?i)(?:mysql|postgres|mongodb|redis)://[^\s<>'\"]{10,}"), "HIGH"),
    ("Connection String", re.compile(r"(?i)(?:server|data source)=[^;]{5,};.*(?:password|pwd)=[^;]+", re.IGNORECASE), "HIGH"),

    # Heroku / Firebase
    ("Heroku API Key", re.compile(r"(?i)heroku(.{0,20})?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"), "HIGH"),
    ("Firebase URL", re.compile(r"https://[a-z0-9-]+\\.firebaseio\\.com"), "LOW"),
    ("Firebase Key", re.compile(r"(?i)firebase(.{0,20})?['\"][A-Za-z0-9]{30,}['\"]"), "MEDIUM"),

    # Azure
    ("Azure Storage Key", re.compile(r"(?i)DefaultEndpointsProtocol=https;AccountName=[^;]+;AccountKey=[A-Za-z0-9+/=]{40,}"), "CRITICAL"),

    # Generic high-entropy strings that look like secrets (base64, hex)
    ("Exposed .env", re.compile(r"(?m)^(?:APP_KEY|DB_PASSWORD|SECRET_KEY|API_SECRET)=.+$"), "HIGH"),
    ("Bearer Token", re.compile(r"(?i)bearer\s+[A-Za-z0-9\-._~+/]{20,}"), "MEDIUM"),
]


def _scan_body(body: str, source_url: str) -> list[Node]:
    found: list[Node] = []
    seen: set[str] = set()

    for name, pattern, severity in _PATTERNS:
        for match in pattern.finditer(body):
            raw = match.group(0)
            # Truncate long matches for display
            display = raw[:80] + ("..." if len(raw) > 80 else "")
            key = f"{name}:{raw[:60]}"
            if key in seen:
                continue
            seen.add(key)

            # Context: surrounding text
            start = max(0, match.start() - 40)
            end = min(len(body), match.end() + 40)
            context = body[start:end].replace("\n", " ").strip()

            found.append(Node(
                type="password" if "key" in name.lower() or "secret" in name.lower() or "token" in name.lower() else "note",
                value=display,
                label=f"[{severity}] {name}",
                data={
                    "secret_type": name,
                    "severity": severity,
                    "raw_match": raw[:200],
                    "context": context[:200],
                    "source_url": source_url,
                },
            ))
    return found


@transform(
    name="web.secrets_scan",
    display_name="Secrets scanner",
    description="Fetches a URL and scans the response body for exposed API keys, tokens, private keys, credentials, and other secrets using 30+ regex patterns.",
    category="recon",
    input_types=["url", "domain"],
    output_types=["password", "note"],
    timeout=30,
    author="builtin",
)
def run(node, api_keys):
    url = node.value.strip()
    if not url.startswith(("http://", "https://")):
        url = f"http://{url}"

    try:
        req = urllib.request.Request(url, headers={"User-Agent": "osint-engine/0.1"})
        with urllib.request.urlopen(req, timeout=15) as resp:
            body = resp.read(2_000_000).decode("utf-8", errors="replace")
    except Exception as exc:
        return [Node(type="note", value=f"fetch error: {exc}", label="fetch error")]

    findings = _scan_body(body, url)
    if not findings:
        return [Node(type="note", value=f"no secrets found on {url}", label="clean")]
    return findings


@transform(
    name="web.secrets_scan_slave",
    display_name="Secrets scanner (via slave)",
    description="Same as secrets_scan but fetches through a slave connection (useful for internal targets). Uses curl on the slave.",
    category="recon",
    input_types=["url", "domain"],
    output_types=["password", "note"],
    requires_slave=True,
    timeout=30,
    author="builtin",
)
def run_slave(node, api_keys, slave):
    url = node.value.strip()
    if not url.startswith(("http://", "https://")):
        url = f"http://{url}"

    result = slave.execute(f"curl -sS -k -L --max-time 15 --max-filesize 2000000 '{url}'", timeout=20)
    if not result.ok:
        return [Node(type="note", value=f"curl error: {result.stderr[:200]}", label="fetch error")]

    findings = _scan_body(result.stdout, url)
    if not findings:
        return [Node(type="note", value=f"no secrets found on {url}", label="clean")]
    return findings
