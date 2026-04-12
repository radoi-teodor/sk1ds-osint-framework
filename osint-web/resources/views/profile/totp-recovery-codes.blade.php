@extends('layouts.app')
@section('title', 'Recovery Codes')
@section('content')
<h1>▸ Recovery codes</h1>

<div class="panel">
    <div class="alert warn" style="margin-bottom:16px;">
        Save these codes in a secure location. They will <strong>not be shown again</strong>.<br>
        Each code can be used <strong>once</strong> in place of a TOTP code during login.
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;max-width:400px;margin:0 auto;">
        @foreach($codes as $code)
            <div style="background:var(--bg-elev-2);border:1px solid var(--border);padding:10px;text-align:center;font-family:var(--font-mono);font-size:16px;letter-spacing:2px;">
                {{ $code }}
            </div>
        @endforeach
    </div>

    <div style="text-align:center;margin-top:20px;">
        <button onclick="copyRecoveryCodes()" class="ghost">COPY ALL</button>
    </div>
</div>

<a href="/profile/security" class="btn mt-3">⟫ DONE</a>

<script>
function copyRecoveryCodes() {
    const codes = @json($codes);
    const text = codes.join('\n');
    if (window.copyToClipboard) {
        window.copyToClipboard(text);
    } else {
        navigator.clipboard.writeText(text).then(() => window.toast && window.toast('copied'));
    }
}
</script>
@endsection
