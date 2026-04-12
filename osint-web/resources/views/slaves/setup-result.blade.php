@extends('layouts.app')
@section('title', 'Setup run — ' . $slave->name)
@section('content')
<h1>▸ Setup: {{ $slave->name }} — {{ $run->script?->name ?? 'script' }}</h1>

<div class="panel">
    <div class="panel-title flex items-center gap-3">
        <span id="run-status" class="job-status job-{{ $run->status }}">{{ strtoupper($run->status) }}</span>
        <span id="run-timing" class="text-dim small"></span>
    </div>

    <div id="run-progress" style="margin-bottom:14px;">
        @if(!$run->isTerminal())
            <div class="job-bar" style="height:4px;"><div class="job-bar-fill" style="width:100%;animation:pulse 1.2s ease-in-out infinite;"></div></div>
        @endif
    </div>

    <div class="panel-title" style="font-size:12px;">stdout</div>
    <pre id="run-stdout" class="mono" style="background:var(--bg-elev-2);padding:14px;border:1px solid var(--border);font-size:12px;max-height:500px;overflow:auto;white-space:pre-wrap;min-height:60px;">{{ $run->stdout ?? 'waiting...' }}</pre>

    <div id="stderr-wrap" style="{{ empty($run->stderr) ? 'display:none' : '' }}">
        <div class="panel-title" style="font-size:12px;color:var(--danger);margin-top:12px;">stderr</div>
        <pre id="run-stderr" class="mono" style="background:var(--bg-elev-2);padding:14px;border:1px solid var(--danger);font-size:12px;max-height:300px;overflow:auto;white-space:pre-wrap;color:var(--danger);">{{ $run->stderr ?? '' }}</pre>
    </div>

    <div id="run-error" class="alert danger mt-3" style="{{ $run->error ? '' : 'display:none' }}">{{ $run->error }}</div>

    <div id="run-exit" class="text-dim small mt-2">{{ $run->exit_code !== null ? "Exit code: {$run->exit_code}" : '' }}</div>
</div>

<div class="flex gap-2 mt-3">
    <a href="/slaves/{{ $slave->id }}/setup" class="btn ghost">← RUN AGAIN</a>
    <a href="/slaves" class="btn">BACK TO SLAVES</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var done = {{ $run->isTerminal() ? 'true' : 'false' }};
    if (done) return;

    var pollUrl = '/api/slaves/{{ $slave->id }}/setup/runs/{{ $run->id }}';
    var statusEl = document.getElementById('run-status');
    var stdoutEl = document.getElementById('run-stdout');
    var stderrEl = document.getElementById('run-stderr');
    var stderrWrap = document.getElementById('stderr-wrap');
    var errorEl = document.getElementById('run-error');
    var exitEl = document.getElementById('run-exit');
    var progressEl = document.getElementById('run-progress');
    var timingEl = document.getElementById('run-timing');

    function poll() {
        csrfFetch(pollUrl).then(function(r) { return r.json(); }).then(function(data) {
            statusEl.textContent = data.status.toUpperCase();
            statusEl.className = 'job-status job-' + data.status;
            if (data.stdout) { stdoutEl.textContent = data.stdout; stdoutEl.scrollTop = stdoutEl.scrollHeight; }
            if (data.stderr) { stderrEl.textContent = data.stderr; stderrWrap.style.display = ''; }
            if (data.error) { errorEl.textContent = data.error; errorEl.style.display = ''; }
            if (data.exit_code !== null) exitEl.textContent = 'Exit code: ' + data.exit_code;
            if (data.started_at && data.finished_at) {
                var ms = new Date(data.finished_at) - new Date(data.started_at);
                timingEl.textContent = (ms / 1000).toFixed(1) + 's';
            } else if (data.started_at) {
                timingEl.textContent = 'running...';
            }
            if (data.status === 'completed' || data.status === 'failed') {
                done = true;
                progressEl.innerHTML = '';
                toast(data.status === 'completed' ? 'Setup completed' : 'Setup failed', data.status === 'completed' ? 'success' : 'danger');
            } else {
                setTimeout(poll, 1500);
            }
        }).catch(function() { setTimeout(poll, 3000); });
    }

    setTimeout(poll, 1000);
});
</script>
@endsection
