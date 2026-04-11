@extends('layouts.app')
@section('title', $project->name)
@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1>▸ {{ $project->name }}</h1>
        @if($project->description)<p class="text-dim">{{ $project->description }}</p>@endif
    </div>
    <form method="POST" action="/projects/{{ $project->id }}" data-confirm="Delete this project and all its graphs?">
        @csrf @method('DELETE')
        <button type="submit" class="ghost danger">DELETE</button>
    </form>
</div>

<div class="panel">
    <div class="panel-title">New investigation graph</div>
    <form method="POST" action="/projects/{{ $project->id }}/graphs" class="stack">
        @csrf
        <input type="hidden" name="type" value="investigation">
        <div class="form-row">
            <label>Graph title</label>
            <input type="text" name="title" required placeholder="e.g. initial recon">
        </div>
        <div>
            <button type="submit">⟫ OPEN CANVAS</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Graphs in this project</div>
    @if($project->graphs->isEmpty())
        <p class="text-dim">No graphs yet.</p>
    @else
        <div class="card-grid">
            @foreach($project->graphs as $g)
                <a href="/graphs/{{ $g->id }}" class="card">
                    <h3>{{ $g->title }}</h3>
                    <p class="meta">{{ $g->type }} · {{ $g->created_at->diffForHumans() }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
