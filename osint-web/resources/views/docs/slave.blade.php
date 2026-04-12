@extends('docs.layout')
@section('title', 'SlaveClient (SSH)')
@section('content')
<h1>SlaveClient (SSH / Local)</h1>
<p>Transforms that need to execute shell commands (nmap, python, custom scripts) declare <code>requires_slave=True</code> and receive a <code>SlaveClient</code> as their third argument.</p>

<h2>Declaring a slave-requiring transform</h2>
<pre>from osint_engine.sdk import transform, Node

@transform(
    name="recon.whois_cli",
    display_name="WHOIS via CLI",
    requires_slave=True,     # ← this is the key
    input_types=["domain"],
    output_types=["note"],
    timeout=30,
)
def run(node, api_keys, slave):     # ← third argument
    result = slave.execute(f"whois {node.value}", timeout=20)
    if not result.ok:
        return [Node(type="note", value=f"error: {result.stderr[:200]}")]
    return [Node(type="note", value=result.stdout[:2000], label=f"whois:{node.value}")]</pre>

<h2>SlaveClient API</h2>

<h3><code>slave.execute(command, timeout=30) → CommandResult</code></h3>
<p>Executes a shell command on the configured slave.</p>
<ul>
    <li><strong>SSH slaves</strong>: runs via <code>paramiko.exec_command</code></li>
    <li><strong>Embedded slave</strong>: runs via <code>subprocess.run(shell=False)</code></li>
</ul>
<p>The <code>command</code> string is parsed by <code>shlex.split()</code>. Any binary is permitted — there are no restrictions on what you can run.</p>

<table>
    <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>command</code></td><td>str</td><td>Shell command string (e.g., <code>"nmap -T4 1.2.3.4"</code>)</td></tr>
        <tr><td><code>timeout</code></td><td>int</td><td>Max seconds. Default 30. SSH and subprocess both enforce this.</td></tr>
    </tbody>
</table>

<h3>CommandResult</h3>
<pre>@dataclass
class CommandResult:
    stdout: str       # standard output
    stderr: str       # standard error
    exit_code: int    # 0 = success

    @property
    def ok(self) -> bool:    # True if exit_code == 0
        ...</pre>

<h3><code>slave.is_embedded → bool</code></h3>
<p>True if the slave runs commands locally (no SSH).</p>

<h2>Slave types</h2>
<table>
    <thead><tr><th>Type</th><th>Description</th><th>Auth</th></tr></thead>
    <tbody>
        <tr><td><strong>SSH</strong></td><td>Connects to a remote server via paramiko</td><td>Password or PEM private key (RSA, Ed25519, ECDSA, DSS — auto-detected)</td></tr>
        <tr><td><strong>Embedded</strong></td><td>Runs on the engine's local machine</td><td>None — uses the engine process's OS user</td></tr>
    </tbody>
</table>

<h2>User workflow</h2>
<p>Before running a slave-requiring transform, the user must:</p>
<ol>
    <li>Configure a slave at <code>/slaves</code> (SSH credentials or embedded)</li>
    <li>Select it from the <strong>Slave</strong> dropdown in the investigation graph's right sidebar</li>
    <li>Then run the transform — the selected slave is used for execution</li>
</ol>
<p>If no slave is selected, the user sees a warning: <em>"This transform requires a slave"</em>.</p>

<h2>Best practices</h2>
<ul>
    <li><strong>Always set a timeout</strong> on <code>slave.execute()</code> — don't rely only on the decorator's timeout.</li>
    <li><strong>Validate the target</strong> before interpolating into commands — check that <code>node.value</code> matches a safe pattern (IP, domain regex).</li>
    <li><strong>Handle errors gracefully</strong> — check <code>result.ok</code> before parsing stdout.</li>
    <li><strong>Multiple execute calls</strong> are fine — the SSH connection is reused within one transform invocation.</li>
</ul>

<div class="callout warn">
    <strong>Security:</strong> Slave credentials are encrypted at rest (AES-256-CBC) and only decrypted at the moment of execution. They are never exposed in the UI or stored in the engine.
</div>
@endsection
