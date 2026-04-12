@extends('docs.layout')
@section('title', 'Getting Started')
@section('content')
<h1>Getting Started</h1>
<p>Create your first transform in under 2 minutes.</p>

<h2>1. Create the file</h2>
<p>Create a new <code>.py</code> file in <code>osint-engine/transforms/</code>:</p>
<pre># transforms/my_first.py
from osint_engine.sdk import transform, Node

@transform(
    name="my.first",
    display_name="My First Transform",
    description="Says hello to any node",
    category="custom",
    input_types=["*"],       # accepts any node type
    output_types=["note"],
)
def run(node, api_keys):
    return [Node(
        type="note",
        value=f"Hello from {node.type}: {node.value}",
        label=f"hello:{node.value[:20]}",
    )]</pre>

<h2>2. Reload the engine</h2>
<p>Either:</p>
<ul>
    <li>Click <strong>"Reload Engine"</strong> on the <code>/transformations</code> page, or</li>
    <li>The editor auto-reloads when you save via <code>/transformations/{name}/edit</code>, or</li>
    <li>Restart the engine process.</li>
</ul>

<h2>3. Use it</h2>
<p>Open any investigation graph, right-click a node → your transform appears in the <strong>"custom"</strong> category. Click it — the result nodes appear connected to the source.</p>

<h2>Alternatively: create from the UI</h2>
<p>Go to <code>/transformations</code> → <strong>+ NEW</strong>. Paste your code, give it a filename, and click <strong>CREATE</strong>. The engine reloads automatically.</p>

<h2>File naming</h2>
<p>File names must match <code>[a-zA-Z0-9_-]+.py</code>. Files starting with <code>_</code> are ignored by the loader.</p>
<p>One file can contain <strong>multiple</strong> <code>@transform</code> decorated functions — they all get registered. Group related transforms in the same file for convenience (e.g., all nmap transforms live in <code>nmap_scan.py</code>).</p>

<h2>Function signature</h2>
<p>Your decorated function receives either 2 or 3 arguments:</p>
<pre># Standard (no slave)
def run(node, api_keys):
    ...

# With slave (requires_slave=True in decorator)
def run(node, api_keys, slave):
    ...</pre>

<table>
    <thead><tr><th>Argument</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>node</code></td><td><code>Node</code></td><td>The input node the user right-clicked. Has <code>.type</code>, <code>.value</code>, <code>.label</code>, <code>.data</code>.</td></tr>
        <tr><td><code>api_keys</code></td><td><code>dict[str, str]</code></td><td>Decrypted API keys matching <code>required_api_keys</code>. Missing keys = missing from dict (not an error).</td></tr>
        <tr><td><code>slave</code></td><td><code>SlaveClient</code></td><td>Only when <code>requires_slave=True</code>. Execute commands via SSH or local subprocess.</td></tr>
    </tbody>
</table>

<h2>Return value</h2>
<p>Return one of:</p>
<ul>
    <li><code>list[Node]</code> — most common. Each Node becomes a child of the input node.</li>
    <li><code>dict</code> with keys <code>nodes</code> (list) and <code>edges</code> (list) — for custom edge relationships.</li>
    <li>A single <code>Node</code>.</li>
    <li><code>None</code> or empty list — no output (no error).</li>
</ul>

<div class="callout">
    If your function raises an exception, the platform catches it and records the error. The user sees it as a failed job — no crash, no data corruption.
</div>
@endsection
