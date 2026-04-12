@extends('layouts.app')
@section('title', 'Generators')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ Generators</h1>
    <a href="/generators/new" class="btn">+ NEW</a>
</div>
<p class="text-dim mb-4">Generators produce string outputs (wordlists, IP lists, etc.) consumed by transforms. They read uploaded files or text input.</p>

@if($engineError)
    <div class="alert danger">Engine offline: {{ $engineError }}</div>
@endif

@php $grouped = collect($generators)->groupBy(fn ($g) => $g['category'] ?? 'other')->sortKeys(); @endphp

<div class="panel">
    <div class="panel-title">Registered generators <span class="text-dim">({{ count($generators) }})</span></div>
    @if(empty($generators))
        <p class="text-dim">None registered.</p>
    @else
        <div class="transform-groups">
        @foreach($grouped as $category => $items)
            <details class="transform-group">
                <summary>
                    <span class="cat-name">{{ $category }}</span>
                    <span class="cat-count">{{ count($items) }}</span>
                </summary>
                <table class="data" style="margin:0;">
                    <thead><tr><th>Name</th><th>Display</th><th>Inputs</th><th></th></tr></thead>
                    <tbody>
                    @foreach($items as $g)
                        <tr>
                            <td class="mono">{{ $g['name'] }}</td>
                            <td>{{ $g['display_name'] ?? '' }}</td>
                            <td class="small">{{ implode(', ', $g['input_types'] ?? []) }}</td>
                            <td><a href="/generators/{{ $g['name'] }}/edit" class="btn ghost">EDIT</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </details>
        @endforeach
        </div>
    @endif
</div>
@endsection
