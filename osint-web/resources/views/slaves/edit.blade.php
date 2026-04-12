@extends('layouts.app')
@section('title', 'Edit slave — ' . $slave->name)
@section('content')
<h1>▸ Edit slave: {{ $slave->name }}</h1>

@if($slave->fingerprint)
<div class="panel" style="padding:12px;">
    <div class="panel-title" style="font-size:12px;">{{ $slave->flagEmoji() }} Current fingerprint</div>
    <div class="flex gap-4" style="font-size:12px;flex-wrap:wrap;">
        @foreach(['whoami','hostname','public_ip','os','country','isp'] as $k)
            @if(!empty($slave->fingerprint[$k]))
            <div><span class="text-dim">{{ $k }}:</span> {{ $slave->fingerprint[$k] }}</div>
            @endif
        @endforeach
    </div>
</div>
@endif

<div class="panel">
    <form method="POST" action="/slaves/{{ $slave->id }}" class="stack" id="slave-form">
        @csrf @method('PUT')
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required value="{{ old('name', $slave->name) }}">
        </div>
        <div class="form-row">
            <label>Type</label>
            <select name="type" id="slave-type" onchange="toggleSshFields()">
                <option value="ssh" {{ old('type',$slave->type)==='ssh'?'selected':'' }}>SSH (remote)</option>
                <option value="embedded" {{ old('type',$slave->type)==='embedded'?'selected':'' }}>Embedded (local server)</option>
            </select>
        </div>
        <div id="ssh-fields">
            <div class="form-row mt-2">
                <label>Host</label>
                <input type="text" name="host" value="{{ old('host', $slave->host) }}">
            </div>
            <div class="form-row mt-2">
                <label>Port</label>
                <input type="text" name="port" value="{{ old('port', $slave->port) }}">
            </div>
            <div class="form-row mt-2">
                <label>Username</label>
                <input type="text" name="username" value="{{ old('username', $slave->username) }}">
            </div>
            <div class="form-row mt-2">
                <label>Auth method</label>
                <select name="auth_method" id="auth-method" onchange="toggleCredField()">
                    <option value="password" {{ old('auth_method',$slave->auth_method)==='password'?'selected':'' }}>Password</option>
                    <option value="key" {{ old('auth_method',$slave->auth_method)==='key'?'selected':'' }}>Private key (PEM)</option>
                </select>
            </div>
            <div class="form-row mt-2" id="cred-password">
                <label>Password <span class="text-dim">(blank = keep current: {{ $slave->maskedPreview() }})</span></label>
                <input type="password" name="credential" autocomplete="new-password">
            </div>
            <div class="form-row mt-2" id="cred-key" style="display:none">
                <label>Private key <span class="text-dim">(blank = keep current)</span></label>
                <textarea name="credential_key" rows="5" class="mono"></textarea>
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit">⟫ SAVE</button>
            <a href="/slaves" class="btn ghost">CANCEL</a>
        </div>
    </form>
</div>

<script>
function toggleSshFields() {
    document.getElementById('ssh-fields').style.display =
        document.getElementById('slave-type').value === 'ssh' ? '' : 'none';
}
function toggleCredField() {
    const method = document.getElementById('auth-method').value;
    document.getElementById('cred-password').style.display = method === 'password' ? '' : 'none';
    document.getElementById('cred-key').style.display = method === 'key' ? '' : 'none';
}
document.getElementById('slave-form').addEventListener('submit', function() {
    if (document.getElementById('auth-method').value === 'key') {
        const keyTA = document.querySelector('[name="credential_key"]');
        const pwInput = document.querySelector('[name="credential"]');
        if (keyTA && keyTA.value) pwInput.value = keyTA.value;
    }
});
toggleSshFields();
toggleCredField();
</script>
@endsection
