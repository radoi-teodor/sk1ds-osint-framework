@extends('layouts.app')
@section('title', 'Setup — ' . $slave->name)
@section('content')
<h1>▸ Setup: {{ $slave->name }}</h1>
<p class="text-dim mb-4">
    {{ $slave->flagEmoji() }}
    {{ $slave->isEmbedded() ? 'Embedded (local)' : $slave->host . ':' . $slave->port }}
    @if($slave->fingerprint) · {{ $slave->fingerprint['os'] ?? '' }} @endif
</p>

@if($scripts->isEmpty())
    <div class="panel">
        <p class="text-dim">No setup scripts defined. <a href="/slaves/scripts">Create one first.</a></p>
    </div>
@else
    <div class="panel">
        <div class="panel-title">Select script to run</div>
        <form method="POST" action="/slaves/{{ $slave->id }}/setup" class="stack">
            @csrf
            <div class="form-row">
                <label>Script</label>
                <select name="script_id" required>
                    @foreach($scripts as $s)
                        <option value="{{ $s->id }}" {{ $s->is_default ? 'selected' : '' }}>
                            {{ $s->name }}{{ $s->is_default ? ' (default)' : '' }}
                            — {{ Str::limit($s->description ?? '', 50) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="callout warn" style="border-left:3px solid var(--warn);background:rgba(255,176,0,0.08);padding:10px 14px;font-size:12px;">
                This will execute the script as bash on the slave. It may install packages, modify system configuration, etc. Make sure you trust the script.
            </div>
            <div class="flex gap-2">
                <button type="submit">⟫ RUN SETUP</button>
                <a href="/slaves" class="btn ghost">CANCEL</a>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-title">Script preview</div>
        <div id="script-preview" style="max-height:400px;overflow:auto;">
            <pre class="mono" style="background:var(--bg-elev-2);padding:14px;border:1px solid var(--border);font-size:12px;white-space:pre-wrap;">{{ $scripts->firstWhere('is_default') ? $scripts->firstWhere('is_default')->script : $scripts->first()->script }}</pre>
        </div>
    </div>

    <script>
    document.querySelector('select[name="script_id"]').addEventListener('change', function() {
        const scripts = @json($scripts->pluck('script', 'id'));
        document.querySelector('#script-preview pre').textContent = scripts[this.value] || '';
    });
    </script>
@endif

@if($runs->isNotEmpty())
<div class="panel">
    <div class="panel-title">Recent runs</div>
    <table class="data">
        <thead><tr><th>Script</th><th>Status</th><th>Exit</th><th>Duration</th><th>When</th><th></th></tr></thead>
        <tbody>
        @foreach($runs as $r)
            <tr>
                <td class="mono small">{{ $r->script?->name ?? '?' }}</td>
                <td><span class="job-status job-{{ $r->status }}">{{ strtoupper($r->status) }}</span></td>
                <td class="mono small">{{ $r->exit_code ?? '—' }}</td>
                <td class="small">
                    @if($r->started_at && $r->finished_at)
                        {{ $r->started_at->diffInSeconds($r->finished_at) }}s
                    @elseif($r->started_at)
                        running...
                    @else
                        —
                    @endif
                </td>
                <td class="small">{{ $r->created_at->diffForHumans() }}</td>
                <td><a href="/slaves/{{ $slave->id }}/setup/runs/{{ $r->id }}" class="btn ghost" style="padding:2px 8px;font-size:10px;">VIEW</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
