@extends('docs.layout')
@section('title', 'Node & Edge')
@section('content')
<h1>Node &amp; Edge</h1>
<p>These are the data classes your transform functions return.</p>

<h2>Node</h2>
<pre>from osint_engine.sdk import Node

node = Node(
    type="email",                    # entity type (see Entity Types page)
    value="alice@example.com",       # the primary value
    label="alice@example.com",       # display label (defaults to value)
    data={"breach": "SomeLeak"},     # arbitrary metadata dict
)</pre>

<table>
    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>type</code></td><td>str</td><td>yes</td><td>Entity type. Drives the node's color, shape and icon in the graph. See <a href="/docs/entity-types">Entity Types</a>.</td></tr>
        <tr><td><code>value</code></td><td>str</td><td>yes</td><td>The primary value (IP, email, domain, hash, etc.). Shown in the node inspector.</td></tr>
        <tr><td><code>label</code></td><td>str | None</td><td>no</td><td>Display label. If omitted, <code>value</code> is used. Truncated to ~30 chars in the graph. Shown on hover and selection.</td></tr>
        <tr><td><code>data</code></td><td>dict</td><td>no</td><td>Arbitrary metadata. Shown in full in the node inspector sidebar. Useful for storing raw API responses, breach names, record context, etc.</td></tr>
    </tbody>
</table>

<h3>Input node</h3>
<p>Your function receives the <strong>input node</strong> — the one the user right-clicked. Access its fields:</p>
<pre>def run(node, api_keys):
    print(node.type)    # "domain"
    print(node.value)   # "example.com"
    print(node.label)   # "example.com"
    print(node.data)    # {"breach": "..."} or {} if manually created</pre>

<div class="callout">
    <strong>Tip:</strong> Nodes produced by other transforms (like Snusbase) carry rich data in <code>node.data</code>. For example, a Snusbase email node has <code>node.data["record"]</code> with the full breach record. Use this for chaining — write "extractor" transforms that read <code>node.data</code> and emit typed child nodes.
</div>

<h2>Edge</h2>
<p>Normally you don't need to create edges — the platform auto-creates an edge from the input node to each output node. But if you need custom edges between output nodes:</p>
<pre>from osint_engine.sdk import Node, Edge

def run(node, api_keys):
    return {
        "nodes": [
            Node(type="ipv4", value="1.1.1.1"),
            Node(type="ipv4", value="2.2.2.2"),
        ],
        "edges": [
            Edge(source="1.1.1.1", target="2.2.2.2", label="peers"),
        ],
    }</pre>

<table>
    <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>source</code></td><td>str</td><td>Source node reference (value or cy_id)</td></tr>
        <tr><td><code>target</code></td><td>str</td><td>Target node reference</td></tr>
        <tr><td><code>label</code></td><td>str | None</td><td>Edge label (optional)</td></tr>
        <tr><td><code>data</code></td><td>dict</td><td>Arbitrary metadata</td></tr>
    </tbody>
</table>
@endsection
