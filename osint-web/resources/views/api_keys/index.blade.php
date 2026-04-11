@extends('layouts.app')
@section('title', 'API Keys')
@section('content')
<h1>▸ API Key vault</h1>
<p class="text-dim mb-4">Encrypted with the app key (AES-256-CBC). Values are decrypted server-side only when passed to the engine.</p>

<div class="panel">
    <div class="panel-title">New API key</div>
    <form method="POST" action="/api-keys" class="stack">
        @csrf
        <div class="form-row">
            <label>Identifier (what transforms reference)</label>
            <input type="text" name="name" required placeholder="C99_API_KEY" pattern="^[A-Z][A-Z0-9_]*$">
        </div>
        <div class="form-row">
            <label>Label (optional)</label>
            <input type="text" name="label" placeholder="My personal c99 key">
        </div>
        <div class="form-row">
            <label>Value</label>
            <input type="password" name="value" required autocomplete="off">
        </div>
        <div><button type="submit">⟫ STORE (ENCRYPT)</button></div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Stored keys</div>
    @if($keys->isEmpty())
        <p class="text-dim">No keys stored yet.</p>
    @else
    <table class="data">
        <thead><tr><th>Name</th><th>Label</th><th>Preview</th><th>Created</th><th></th></tr></thead>
        <tbody>
        @foreach($keys as $k)
            <tr>
                <td class="mono">{{ $k['name'] }}</td>
                <td>{{ $k['label'] }}</td>
                <td class="mono">{{ $k['preview'] }}</td>
                <td class="small">{{ $k['created_at']?->diffForHumans() }}</td>
                <td>
                    <form method="POST" action="/api-keys/{{ $k['id'] }}" data-confirm="Delete this key?">
                        @csrf @method('DELETE')
                        <button class="ghost danger">DEL</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
