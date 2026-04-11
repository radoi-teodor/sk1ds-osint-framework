@extends('layouts.app')
@section('title', 'Projects')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ Projects</h1>
</div>

<div class="panel">
    <div class="panel-title">New project</div>
    <form method="POST" action="/projects" class="stack">
        @csrf
        <div class="form-row">
            <label>Project name</label>
            <input type="text" name="name" required placeholder="e.g. TARGET-ALPHA">
        </div>
        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="What are you investigating?"></textarea>
        </div>
        <div>
            <button type="submit">⟫ CREATE</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Existing projects</div>
    @if($projects->isEmpty())
        <p class="text-dim">No projects yet.</p>
    @else
    <div class="card-grid">
        @foreach($projects as $p)
            <a href="/projects/{{ $p->id }}" class="card">
                <h3>{{ $p->name }}</h3>
                <p class="meta">{{ Str::limit($p->description ?? 'no description', 80) }}</p>
                <p class="meta">{{ $p->graphs_count }} graph(s) · {{ $p->created_at?->diffForHumans() }}</p>
            </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
