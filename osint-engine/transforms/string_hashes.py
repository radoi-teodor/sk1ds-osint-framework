"""Compute common hashes of a string."""

import hashlib

from osint_engine.sdk import Node, transform


@transform(
    name="string.hashes",
    display_name="String → hashes",
    description="Emits MD5 / SHA1 / SHA256 / SHA512 hashes of the input value.",
    category="crypto",
    input_types=["*"],
    output_types=["hash"],
    author="builtin",
)
def run(node, api_keys):
    data = str(node.value).encode("utf-8")
    return [
        Node(type="hash", value=hashlib.md5(data).hexdigest(), label=f"md5:{hashlib.md5(data).hexdigest()[:12]}", data={"algo": "md5"}),
        Node(type="hash", value=hashlib.sha1(data).hexdigest(), label=f"sha1:{hashlib.sha1(data).hexdigest()[:12]}", data={"algo": "sha1"}),
        Node(type="hash", value=hashlib.sha256(data).hexdigest(), label=f"sha256:{hashlib.sha256(data).hexdigest()[:12]}", data={"algo": "sha256"}),
        Node(type="hash", value=hashlib.sha512(data).hexdigest(), label=f"sha512:{hashlib.sha512(data).hexdigest()[:12]}", data={"algo": "sha512"}),
    ]
