@extends('layouts.app')
@section('title', 'New transformation')
@section('content')
<h1>▸ New transformation</h1>
<p class="text-dim mb-4">Drops a new <code>.py</code> file into the engine's <code>transforms/</code> directory.</p>

<div class="panel">
    <form method="POST" action="/transformations" class="stack">
        @csrf
        <div class="form-row">
            <label>Filename</label>
            <input type="text" name="filename" required placeholder="my_thing.py" pattern="^[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.py$">
        </div>
        <div class="form-row">
            <label>Source</label>
            <textarea name="source" rows="22" class="mono" required>from osint_engine.sdk import transform, Node


@transform(
    name="my.thing",
    display_name="My Thing",
    description="Describe what it does",
    category="custom",
    input_types=["domain"],
    output_types=["note"],
    required_api_keys=[],
    timeout=10,
)
def run(node, api_keys):
    return [Node(type="note", value=f"hello {node.value}")]
</textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit">⟫ CREATE</button>
            <a class="btn ghost" href="/transformations">CANCEL</a>
        </div>
    </form>
</div>
@endsection
