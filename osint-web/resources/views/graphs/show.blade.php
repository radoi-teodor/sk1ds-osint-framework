@extends('layouts.app')
@section('title', $graph->title)
@section('main_class', 'fullbleed')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/cytoscape@3.30.2/dist/cytoscape.min.js"></script>
@endpush

@section('content')
<div class="graph-page">

    {{-- LEFT: transforms palette --}}
    <aside class="graph-sidebar">
        <div class="panel-title" style="margin-top:0">{{ $graph->isTemplate() ? 'Template Steps' : 'Transforms' }}</div>
        @if($engineError)
            <div class="alert danger">engine offline: {{ $engineError }}</div>
        @endif

        @if($graph->isTemplate())
            <button class="w-full mb-2" onclick="templateAddInput()">+ Input slot</button>
            <div class="text-dim small mb-2">Shift-click one node then another to connect.</div>
            <div class="transform-list">
                @foreach($transforms as $t)
                    <div class="transform-item" onclick="templateAddTransform('{{ $t['name'] }}')">
                        <div class="name">{{ $t['display_name'] ?? $t['name'] }}</div>
                        <div class="desc">{{ Str::limit($t['description'] ?? '', 80) }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-dim small mb-2">Right-click a node to apply a transform.</div>
            <div class="transform-list">
                @forelse($transforms as $t)
                    <div class="transform-item" title="{{ $t['description'] ?? '' }}">
                        <div class="name">{{ $t['display_name'] ?? $t['name'] }}</div>
                        <div class="desc">
                            in: {{ implode(',', $t['input_types'] ?? []) }}
                            · out: {{ implode(',', $t['output_types'] ?? []) }}
                        </div>
                    </div>
                @empty
                    <div class="text-dim small">No transforms available.</div>
                @endforelse
            </div>
        @endif
    </aside>

    {{-- CENTER: canvas --}}
    <div class="graph-canvas-wrap">
        <div id="cy"></div>

        <div class="graph-toolbar">
            @unless($graph->isTemplate())
                <button class="ghost" onclick="graphAddNode()">+ node</button>
            @endunless
            <button class="ghost" onclick="graphFit()">fit</button>
            <button class="ghost" onclick="graphLayout()">layout</button>
            <button class="ghost" onclick="graphReload()">reload</button>
        </div>

        <div class="graph-minimap" id="minimap">
            <canvas id="mini-canvas" style="position:absolute;inset:0;width:100%;height:100%;"></canvas>
            <div id="mini-viewport" style="position:absolute;border:1px solid var(--accent);background:rgba(0,255,156,0.08);pointer-events:auto;cursor:grab;"></div>
        </div>

        <div class="context-menu" id="ctx-menu"></div>
    </div>

    {{-- RIGHT: node inspector + templates --}}
    <aside class="graph-sidebar-right">
        <div class="panel-title" style="margin-top:0">{{ $graph->title }}</div>
        <div class="text-dim small mb-3">
            {{ $graph->type }} · {{ $graph->project?->name ?? '—' }}
        </div>
        @unless($graph->isTemplate())
        <div class="mb-4">
            <div class="panel-title">Run template</div>
            <div class="text-dim small mb-2">Select one or more nodes, then pick a template.</div>
            @forelse($templates as $tpl)
                <button class="ghost w-full mb-2" onclick="runTemplate({{ $tpl->id }})">▶ {{ $tpl->title }}</button>
            @empty
                <div class="text-dim small">No templates defined yet.</div>
            @endforelse
        </div>
        @endunless
        <div class="panel-title">Active jobs</div>
        <div id="jobs-panel" class="jobs-panel">
            <div class="text-dim small">no active jobs</div>
        </div>

        <div class="panel-title" style="margin-top:16px;">Selected node</div>
        <div id="selected-node" class="selected-node-box">
            <div class="text-dim small">Click a node to inspect.</div>
        </div>
        <hr>
        <form method="POST" action="/graphs/{{ $graph->id }}" data-confirm="Delete this graph?">
            @csrf @method('DELETE')
            <button type="submit" class="ghost danger w-full">DELETE GRAPH</button>
        </form>
    </aside>
</div>

<script>
    window.GRAPH_CONFIG = {
        graphId: {{ $graph->id }},
        graphType: @json($graph->type),
        apiBase: {!! json_encode('/api/graphs/' . $graph->id) !!},
        entityTypes: @json(\App\Support\EntityTypes::all()),
        transforms: @json($transforms),
        templates: @json($templates),
    };
</script>
<script src="{{ asset('js/graph.js') }}" defer></script>
@endsection
