@extends('layouts.guest')
@section('subtitle', 'two-factor authentication')
@section('content')
<p class="text-dim small mb-3">Enter the 6-digit code from your authenticator app, or a recovery code.</p>
<form method="POST" action="/auth/totp-challenge" class="stack">
    @csrf
    <div class="form-row">
        <label>Authentication code</label>
        <input type="text" name="code" required autofocus
               inputmode="numeric" autocomplete="one-time-code"
               maxlength="20" placeholder="000000"
               style="text-align:center;font-size:22px;letter-spacing:6px;">
    </div>
    <button type="submit" class="w-full">⟫ VERIFY</button>
</form>
<form method="POST" action="/auth/totp-cancel" style="margin-top:12px;">
    @csrf
    <button type="submit" class="btn-link w-full" style="text-align:center;display:block;font-size:11px;color:var(--text-dim);">← back to login</button>
</form>
@endsection
