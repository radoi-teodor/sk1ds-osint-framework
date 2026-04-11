@extends('layouts.app')
@section('title', 'Edit ' . $name)
@section('main_class', 'fullbleed')
@section('content')
<div class="editor-wrap">
    <aside class="editor-sidebar">
        <div class="panel-title" style="margin-top:0">{{ $filename }}</div>
        <div class="text-dim small mb-3">Transform: <span class="mono">{{ $name }}</span></div>

        <h3 style="font-size:12px;">SDK reference</h3>
        <pre class="small" style="background:var(--bg-elev-2);padding:10px;border:1px solid var(--border);white-space:pre-wrap;">from osint_engine.sdk import transform, Node

@transform(
    name="my.thing",
    display_name="My Thing",
    description="...",
    category="custom",
    input_types=["domain"],
    output_types=["ipv4"],
    required_api_keys=[],
    timeout=10,
)
def run(node, api_keys):
    return [Node(type="ipv4", value="1.2.3.4")]</pre>

        <hr>
        <form method="POST" action="/transformations/{{ $name }}" data-confirm="Delete this transform file?">
            @csrf @method('DELETE')
            <button type="submit" class="ghost danger w-full">DELETE</button>
        </form>
        <a href="/transformations" class="btn ghost w-full mt-2" style="display:block;text-align:center;">← BACK</a>
    </aside>

    <div class="editor-main">
        <div class="editor-toolbar">
            <button id="save-btn" onclick="editorSave('/transformations/{{ $name }}')">⟫ SAVE (Ctrl+S)</button>
            <button class="ghost" onclick="editorValidate()">VALIDATE</button>
            <button class="ghost" onclick="editorReload()">RELOAD ENGINE</button>
            <span class="status" id="editor-status">ready</span>
        </div>
        <div class="editor-container" id="editor-mount"></div>
        <textarea id="source-textarea">{{ $source }}</textarea>
    </div>
</div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('js/editor.js') }}"></script>
@endpush
