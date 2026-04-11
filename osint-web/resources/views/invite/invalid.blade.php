@extends('layouts.guest')
@section('subtitle', 'access denied')
@section('content')
<div class="alert danger">
    Invite invalid, already used, or expired.
</div>
<p class="text-dim small">Contact an administrator for a new link.</p>
<a href="/login" class="btn ghost w-full" style="text-align:center;display:block;margin-top:12px;">Return to login</a>
@endsection
