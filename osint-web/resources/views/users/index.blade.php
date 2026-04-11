@extends('layouts.app')
@section('title', 'Operators')
@section('content')
<h1>▸ Operators</h1>
<p class="text-dim mb-4">All operators have access to every project. Invite new operators with one-shot crypto-strong tokens (no email needed).</p>

<div class="panel">
    <div class="panel-title">Generate invite link</div>
    <form method="POST" action="/users/invite" class="stack">
        @csrf
        <div class="form-row">
            <label>Internal note (optional)</label>
            <input type="text" name="note" placeholder="for john.doe">
        </div>
        <div><button type="submit">⟫ GENERATE LINK</button></div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Registered operators</div>
    <table class="data">
        <thead><tr><th>Name</th><th>Email</th><th>Admin</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td class="mono small">{{ $u->email }}</td>
                <td>{!! $u->is_admin ? '<span class="text-accent">ADMIN</span>' : '—' !!}</td>
                <td class="small">{{ $u->created_at?->diffForHumans() }}</td>
                <td>
                    @if($u->id !== auth()->id())
                    <form method="POST" action="/users/{{ $u->id }}" data-confirm="Delete {{ $u->email }}?">
                        @csrf @method('DELETE')
                        <button class="ghost danger">DEL</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="panel">
    <div class="panel-title">Invite links</div>
    @if($invites->isEmpty())
        <p class="text-dim">No invites issued.</p>
    @else
    <table class="data">
        <thead><tr><th>Link</th><th>Created by</th><th>Expires</th><th>Used by</th></tr></thead>
        <tbody>
        @foreach($invites as $i)
            <tr>
                <td class="mono small">
                    @if($i->used_at)
                        <span class="text-dim">used</span>
                    @else
                        <span class="pre">{{ $invite_base_url }}/{{ $i->token }}</span>
                        <button class="ghost" style="padding:2px 8px;font-size:10px;" onclick="navigator.clipboard.writeText('{{ $invite_base_url }}/{{ $i->token }}');toast('copied');">COPY</button>
                    @endif
                </td>
                <td class="small">{{ $i->creator?->name ?? '—' }}</td>
                <td class="small">{{ $i->expires_at?->diffForHumans() }}</td>
                <td class="small">{{ $i->user?->name ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
