@extends('docs.layout')
@section('title', 'Overview')
@section('content')
<h1>Transform SDK</h1>
<p>This documentation covers everything you need to write custom transformations for the {{ config('app.name') }} platform.</p>

<p>A <strong>transformation</strong> is a Python function that receives a graph node, optionally API keys and/or a slave connection, and returns new nodes to add to the investigation graph.</p>

<div class="callout">
    Transforms are Python files dropped into <code>osint-engine/transforms/*.py</code>. The engine discovers them automatically at startup and on reload — no registration, no database entries, no restarts.
</div>

<h2>Quick example</h2>
<pre>from osint_engine.sdk import transform, Node

@transform(
    name="my.probe",
    display_name="My Probe",
    description="Does something useful",
    category="custom",
    input_types=["domain"],
    output_types=["note"],
    timeout=15,
)
def run(node, api_keys):
    return [Node(type="note", value=f"probed: {node.value}")]</pre>

<p>Drop this file into <code>transforms/</code>, hit <strong>Reload Engine</strong> in the UI, and "My Probe" instantly appears in the context menu of every <code>domain</code> node.</p>

<h2>Contents</h2>
<table>
    <tbody>
    @foreach($pages as $slug => $meta)
        <tr>
            <td><a href="/docs/{{ $slug }}"><strong>{{ $meta['icon'] }} {{ $meta['title'] }}</strong></a></td>
            <td class="text-dim">
                @switch($slug)
                    @case('getting-started') How to create your first transform @break
                    @case('decorator') All parameters of the @transform() decorator @break
                    @case('node-edge') Node and Edge data classes @break
                    @case('slave') Execute commands on remote servers via SSH @break
                    @case('api-keys') Store and use API keys securely @break
                    @case('entity-types') Available node types with colors and shapes @break
                    @case('examples') Real-world transform examples @break
                @endswitch
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Architecture at a glance</h2>
<pre>Browser (Cytoscape graph)
  │  right-click node → pick transform
  ▼
Laravel (queue job)
  │  decrypts API keys + slave credentials
  │  POST /transforms/{name}/run
  ▼
Python FastAPI engine
  │  looks up @transform registry
  │  calls your run() function in a thread pool
  ▼
Your transform code
  │  receives: node, api_keys, [slave]
  │  returns: list[Node]
  ▼
New nodes appear in the investigation graph</pre>
@endsection
