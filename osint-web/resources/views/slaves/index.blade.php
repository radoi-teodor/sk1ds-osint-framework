@extends('layouts.app')
@section('title', 'Slaves')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ Slaves</h1>
    <div class="flex gap-2">
        <a href="/slaves/scripts" class="btn ghost">SETUP SCRIPTS</a>
        <a href="/slaves/create" class="btn">+ NEW SLAVE</a>
    </div>
</div>
<p class="text-dim mb-4">SSH connections to remote servers (or the local embedded server). Used by transforms that require shell execution.</p>

<div class="panel">
    <div class="panel-title">Registered slaves</div>
    @if($slaves->isEmpty())
        <p class="text-dim">No slaves configured yet.</p>
    @else
    <table class="data">
        <thead>
        <tr>
            <th>Name</th><th>Type</th><th>Host</th><th>User</th>
            <th>Auth</th><th>Status</th><th>Last tested</th><th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($slaves as $s)
            <tr>
                <td class="mono">{{ $s->name }}</td>
                <td>
                    @if($s->isEmbedded())
                        <span class="text-accent">embedded</span>
                    @else
                        ssh
                    @endif
                </td>
                <td class="mono small">{{ $s->host ?? '—' }}:{{ $s->port }}</td>
                <td class="small">{{ $s->username ?? '—' }}</td>
                <td class="small">{{ $s->maskedPreview() }}</td>
                <td class="small">
                    @if($s->fingerprint)
                        <span title="{{ json_encode($s->fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}">
                            {{ $s->flagEmoji() }} {{ ($s->fingerprint['whoami'] ?? '') . '@' . ($s->fingerprint['hostname'] ?? '') }}
                            <span class="text-dim">· {{ $s->fingerprint['public_ip'] ?? '' }}</span>
                        </span>
                    @else
                        <span class="text-dim">not probed</span>
                    @endif
                </td>
                <td class="small">{{ $s->last_tested_at?->diffForHumans() ?? '—' }}</td>
                <td>
                    <div class="flex gap-2">
                        <form method="POST" action="/slaves/{{ $s->id }}/test">
                            @csrf
                            <button class="ghost" title="Test connection and refresh info">TEST</button>
                        </form>
                        <a href="/slaves/{{ $s->id }}/setup" class="btn ghost" title="Run setup script">SETUP</a>
                        <a href="/slaves/{{ $s->id }}/edit" class="btn ghost">EDIT</a>
                        <form method="POST" action="/slaves/{{ $s->id }}" data-confirm="Delete slave {{ $s->name }}?">
                            @csrf @method('DELETE')
                            <button class="ghost danger">DEL</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Fingerprint detail cards --}}
    @foreach($slaves->filter(fn($s) => $s->fingerprint) as $s)
        <div class="panel mt-3" style="padding:14px;">
            <div class="panel-title" style="font-size:13px;">{{ $s->flagEmoji() }} {{ $s->name }} — fingerprint</div>
            <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;font-size:12px;">
                @foreach(['whoami','hostname','public_ip','os','kernel','country','isp'] as $k)
                    @if(!empty($s->fingerprint[$k]))
                    <div>
                        <div class="text-dim small" style="text-transform:uppercase;">{{ $k }}</div>
                        <div class="mono" style="word-break:break-all;">{{ $s->fingerprint[$k] }}</div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
    @endif
</div>
@endsection
