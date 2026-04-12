@extends('layouts.app')
@section('title', 'Enable 2FA')
@section('content')
<h1>▸ Enable two-factor authentication</h1>

<div class="panel">
    <div class="panel-title">1. Scan QR code</div>
    <p class="text-dim small mb-3">Open your authenticator app and scan this code:</p>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
        <div style="background:#fff;padding:12px;display:inline-block;">
            {!! $qrSvg !!}
        </div>
        <div>
            <p class="small text-dim">Can't scan? Enter this key manually:</p>
            <code style="font-size:16px;letter-spacing:3px;display:block;margin:8px 0;word-break:break-all;">{{ $secret }}</code>
            <p class="small text-dim">Account: {{ auth()->user()->email }}</p>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-title">2. Verify code</div>
    <p class="text-dim small mb-3">Enter the 6-digit code your app shows to confirm setup:</p>

    <form method="POST" action="/profile/security/totp/enable" class="stack" style="max-width:300px;">
        @csrf
        <div class="form-row">
            <label>Verification code</label>
            <input type="text" name="code" required autofocus
                   inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" placeholder="000000"
                   style="text-align:center;font-size:22px;letter-spacing:6px;">
        </div>
        <button type="submit">⟫ VERIFY &amp; ENABLE</button>
    </form>
</div>

<a href="/profile/security" class="btn ghost mt-2">← CANCEL</a>
@endsection
