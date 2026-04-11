@extends('layouts.guest')
@section('subtitle', 'initial setup // create admin operator')
@section('content')
<p class="text-dim small mb-3">No operators registered. Create the first administrator to begin.</p>
<form method="POST" action="/setup" class="stack">
    @csrf
    <div class="form-row">
        <label>Operator name</label>
        <input type="text" name="name" required autofocus value="{{ old('name') }}">
    </div>
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required value="{{ old('email') }}">
    </div>
    <div class="form-row">
        <label>Password (min 8)</label>
        <input type="password" name="password" required>
    </div>
    <div class="form-row">
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
    </div>
    <button type="submit" class="w-full">⟫ INITIALIZE</button>
</form>
@endsection
