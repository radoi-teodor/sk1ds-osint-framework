@extends('layouts.app')
@section('title', 'Security')
@section('content')
<h1>▸ Security</h1>
<p class="text-dim mb-4">Manage two-factor authentication for your account.</p>

<div class="panel">
    <div class="panel-title">Two-factor authentication (TOTP)</div>

    @if($user->hasTotpEnabled())
        <div class="alert success" style="margin-bottom:16px;">
            ✓ Two-factor authentication is <strong>enabled</strong>.
        </div>

        <div class="card-grid" style="grid-template-columns:1fr 1fr;gap:16px;">
            <div class="panel" style="margin:0;">
                <div class="panel-title" style="font-size:12px;">Disable 2FA</div>
                <p class="text-dim small">Enter your password to confirm.</p>
                <form method="POST" action="/profile/security/totp/disable" class="stack">
                    @csrf
                    <div class="form-row">
                        <label>Current password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="danger">DISABLE 2FA</button>
                </form>
            </div>

            <div class="panel" style="margin:0;">
                <div class="panel-title" style="font-size:12px;">Regenerate recovery codes</div>
                <p class="text-dim small">This invalidates all existing recovery codes.</p>
                <form method="POST" action="/profile/security/totp/recovery-codes" class="stack">
                    @csrf
                    <div class="form-row">
                        <label>Current password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="ghost">REGENERATE CODES</button>
                </form>
            </div>
        </div>
    @else
        <div class="alert warn" style="margin-bottom:16px;">
            ✗ Two-factor authentication is <strong>not enabled</strong>.
        </div>
        <p class="mb-3">Adding 2FA protects your account even if your password is compromised. You'll need an authenticator app like <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>1Password</strong>.</p>
        <a href="/profile/security/totp/enable" class="btn">⟫ ENABLE 2FA</a>
    @endif
</div>
@endsection
