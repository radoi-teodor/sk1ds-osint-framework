@extends('layouts.app')
@section('title', 'Transformations')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ Transformations</h1>
    <div class="flex gap-2">
        <a href="/transformations/new" class="btn">+ NEW</a>
        <form method="POST" action="/api/transformations/reload" onsubmit="event.preventDefault(); csrfFetch('/api/transformations/reload',{method:'POST'}).then(()=>toast('Engine reloaded'));">
            @csrf
            <button type="submit" class="ghost">RELOAD ENGINE</button>
        </form>
    </div>
</div>

@if($engineError)
    <div class="alert danger">Engine offline: {{ $engineError }}</div>
@endif
@if(!empty($loadErrors))
    <div class="alert warn">
        <strong>Load errors:</strong>
        <ul>@foreach($loadErrors as $err)<li>{{ $err['file'] ?? '?' }}: {{ $err['error'] ?? '?' }}</li>@endforeach</ul>
    </div>
@endif

<div class="panel">
    <div class="panel-title">Registered transforms</div>
    @if(empty($transforms))
        <p class="text-dim">None registered.</p>
    @else
    <table class="data">
        <thead>
        <tr>
            <th>Name</th><th>Display</th><th>Category</th>
            <th>In</th><th>Out</th><th>Keys</th><th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($transforms as $t)
            <tr>
                <td class="mono">{{ $t['name'] }}</td>
                <td>{{ $t['display_name'] ?? '' }}</td>
                <td>{{ $t['category'] ?? '' }}</td>
                <td class="small">{{ implode(', ', $t['input_types'] ?? []) }}</td>
                <td class="small">{{ implode(', ', $t['output_types'] ?? []) }}</td>
                <td class="small">{{ implode(', ', $t['required_api_keys'] ?? []) }}</td>
                <td>
                    <a href="/transformations/{{ $t['name'] }}/edit" class="btn ghost">EDIT</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
