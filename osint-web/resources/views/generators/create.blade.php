@extends('layouts.app')
@section('title', 'New generator')
@section('content')
<h1>▸ New generator</h1>
<div class="panel">
    <form method="POST" action="/generators" class="stack">
        @csrf
        <div class="form-row">
            <label>Filename</label>
            <input type="text" name="filename" required placeholder="my_gen.py" pattern="^[a-zA-Z0-9_][a-zA-Z0-9_\-]*\.py$">
        </div>
        <div class="form-row">
            <label>Source</label>
            <textarea name="source" rows="20" class="mono" required>from osint_engine.generator_sdk import generator, GeneratorInputs


@generator(
    name="my.gen",
    display_name="My Generator",
    description="Describe what it produces",
    category="custom",
    input_types=["file"],
    timeout=10,
)
def run(inputs: GeneratorInputs) -> str:
    return inputs.read_files()
</textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit">⟫ CREATE</button>
            <a class="btn ghost" href="/generators">CANCEL</a>
        </div>
    </form>
</div>
@endsection
