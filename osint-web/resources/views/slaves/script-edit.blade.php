@extends('layouts.app')
@section('title', 'Edit script — ' . $script->name)
@section('content')
<h1>▸ Edit: {{ $script->name }}</h1>

<div class="panel">
    <form method="POST" action="/slaves/scripts/{{ $script->id }}" class="stack">
        @csrf @method('PUT')
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required value="{{ old('name', $script->name) }}">
        </div>
        <div class="form-row">
            <label>Description</label>
            <input type="text" name="description" value="{{ old('description', $script->description) }}">
        </div>
        <div class="form-row">
            <label>Script (bash)</label>
            <textarea name="script" rows="20" class="mono" required>{{ old('script', $script->script) }}</textarea>
        </div>
        <div class="flex gap-2">
            <button type="submit">⟫ SAVE</button>
            <a href="/slaves/scripts" class="btn ghost">CANCEL</a>
        </div>
    </form>
</div>
@endsection
