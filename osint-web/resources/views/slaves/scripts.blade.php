@extends('layouts.app')
@section('title', 'Setup Scripts')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ Slave setup scripts</h1>
    <a href="/slaves" class="btn ghost">← SLAVES</a>
</div>
<p class="text-dim mb-4">Bash scripts that install tools on slaves. Run them via the <strong>SETUP</strong> button on each slave.</p>

<div class="panel">
    <div class="panel-title">New script</div>
    <form method="POST" action="/slaves/scripts" class="stack">
        @csrf
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required placeholder="e.g. recon-tools">
        </div>
        <div class="form-row">
            <label>Description</label>
            <input type="text" name="description" placeholder="Installs nmap, feroxbuster, etc.">
        </div>
        <div class="form-row">
            <label>Script (bash)</label>
            <textarea name="script" rows="14" class="mono" required>#!/bin/bash
set -e
export DEBIAN_FRONTEND=noninteractive
export DEBCONF_NONINTERACTIVE_SEEN=true
export NEEDRESTART_MODE=a

echo "=== Updating package list ==="
sudo apt-get update -qq

echo "=== Installing nmap ==="
sudo apt-get install -y -qq nmap

echo "=== Installing feroxbuster ==="
if ! command -v feroxbuster; then
  curl -sL https://raw.githubusercontent.com/epi052/feroxbuster/main/install-nix.sh | sudo bash
  sudo mv feroxbuster /usr/local/bin/ 2>/dev/null || true
fi

echo "=== Installing common tools ==="
sudo apt-get install -y -qq curl wget whois dnsutils net-tools

echo "=== Done ==="
nmap --version
which feroxbuster || echo "feroxbuster not in PATH"</textarea>
        </div>
        <div><button type="submit">⟫ CREATE</button></div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Existing scripts</div>
    @if($scripts->isEmpty())
        <p class="text-dim">No scripts defined.</p>
    @else
    <table class="data">
        <thead><tr><th>Name</th><th>Description</th><th>Default</th><th></th></tr></thead>
        <tbody>
        @foreach($scripts as $s)
            <tr>
                <td class="mono">{{ $s->name }}</td>
                <td class="small">{{ $s->description }}</td>
                <td>
                    @if($s->is_default)
                        <span class="text-accent">DEFAULT</span>
                    @else
                        <form method="POST" action="/slaves/scripts/{{ $s->id }}/default" style="display:inline">
                            @csrf
                            <button class="ghost" style="padding:2px 8px;font-size:10px;">SET DEFAULT</button>
                        </form>
                    @endif
                </td>
                <td>
                    <div class="flex gap-2">
                        <a href="/slaves/scripts/{{ $s->id }}/edit" class="btn ghost">EDIT</a>
                        <form method="POST" action="/slaves/scripts/{{ $s->id }}" data-confirm="Delete script?">
                            @csrf @method('DELETE')
                            <button class="ghost danger">DEL</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
