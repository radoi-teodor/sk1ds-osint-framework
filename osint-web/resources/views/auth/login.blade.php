@extends('layouts.guest')
@section('subtitle', 'authorized operators only')
@section('content')
<form method="POST" action="/login" class="stack">
    @csrf
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required autofocus value="{{ old('email') }}">
    </div>
    <div class="form-row">
        <label>Password</label>
        <input type="password" name="password" required>
    </div>
    <div class="form-row">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="remember" style="width:auto"> remember this terminal
        </label>
    </div>
    <button type="submit" class="w-full">⟫ AUTHENTICATE</button>
</form>
@endsection
