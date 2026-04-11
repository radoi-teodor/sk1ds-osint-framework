@extends('layouts.app')
@section('title', 'Templates')
@section('content')
<h1>▸ Investigation templates</h1>
<p class="text-dim mb-4">Reusable, hierarchical chains of transformations. Apply them to one or more nodes inside any investigation.</p>

<div class="panel">
    <div class="panel-title">New template</div>
    <form method="POST" action="/templates" class="stack">
        @csrf
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="title" required placeholder="e.g. Domain recon">
        </div>
        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="2"></textarea>
        </div>
        <div><button type="submit">⟫ CREATE TEMPLATE</button></div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Existing templates</div>
    @if($templates->isEmpty())
        <p class="text-dim">No templates yet.</p>
    @else
    <div class="card-grid">
        @foreach($templates as $t)
            <a href="/graphs/{{ $t->id }}" class="card">
                <h3>{{ $t->title }}</h3>
                <p class="meta">{{ $t->nodes_count }} step(s) · {{ $t->created_at->diffForHumans() }}</p>
                @if($t->description)<p class="meta">{{ Str::limit($t->description, 80) }}</p>@endif
            </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
