@extends('layouts.app')
@section('title', 'New slave')
@section('content')
<h1>▸ New slave</h1>
<p class="text-dim mb-4">Add an SSH connection to a remote server, or register the embedded (local) slave.</p>

<div class="panel">
    <form method="POST" action="/slaves" class="stack" id="slave-form">
        @csrf
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required value="{{ old('name') }}" placeholder="e.g. vps-eu-1">
        </div>
        <div class="form-row">
            <label>Type</label>
            <select name="type" id="slave-type" onchange="toggleSshFields()">
                <option value="ssh" {{ old('type','ssh')==='ssh'?'selected':'' }}>SSH (remote)</option>
                <option value="embedded" {{ old('type')==='embedded'?'selected':'' }}>Embedded (local server)</option>
            </select>
        </div>
        <div id="ssh-fields">
            <div class="form-row mt-2">
                <label>Host</label>
                <input type="text" name="host" value="{{ old('host') }}" placeholder="192.168.1.100 or vpn.example.com">
            </div>
            <div class="form-row mt-2">
                <label>Port</label>
                <input type="text" name="port" value="{{ old('port', 22) }}" placeholder="22">
            </div>
            <div class="form-row mt-2">
                <label>Username</label>
                <input type="text" name="username" value="{{ old('username') }}" placeholder="root">
            </div>
            <div class="form-row mt-2">
                <label>Auth method</label>
                <select name="auth_method" id="auth-method" onchange="toggleCredField()">
                    <option value="password" {{ old('auth_method','password')==='password'?'selected':'' }}>Password</option>
                    <option value="key" {{ old('auth_method')==='key'?'selected':'' }}>Private key (PEM)</option>
                </select>
            </div>
            <div class="form-row mt-2" id="cred-password">
                <label>Password</label>
                <input type="password" name="credential" autocomplete="new-password">
            </div>
            <div class="form-row mt-2" id="cred-key" style="display:none">
                <label>Private key (paste PEM)</label>
                <textarea name="credential_key" rows="6" class="mono" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;..."></textarea>
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit">⟫ CREATE &amp; PROBE</button>
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
// When key method selected, move value from textarea to the hidden credential field on submit
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
