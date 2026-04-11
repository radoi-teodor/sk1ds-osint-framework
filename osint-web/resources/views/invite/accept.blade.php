@extends('layouts.guest')
@section('subtitle', 'invite accepted // create your credentials')
@section('content')
<p class="text-dim small mb-3">Invited by {{ $invite->creator?->name ?? 'system' }} · expires {{ $invite->expires_at->diffForHumans() }}</p>
<form method="POST" action="{{ route('invite.accept', $invite->token) }}" class="stack">
    @csrf
    <div class="form-row">
        <label>Operator name</label>
        <input type="text" name="name" required autofocus>
    </div>
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required>
    </div>
    <div class="form-row">
        <label>Password (min 8)</label>
        <input type="password" name="password" required>
    </div>
    <div class="form-row">
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
    </div>
    <button type="submit" class="w-full">⟫ ACCEPT INVITE</button>
</form>
@endsection
