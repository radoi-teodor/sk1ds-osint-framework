@extends('layouts.app')
@section('title', 'File Manager')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h1>▸ File Manager</h1>
    <div class="flex gap-2">
        <button class="ghost" onclick="fmNewFolder()">+ FOLDER</button>
        <label class="btn" style="cursor:pointer;">
            ⟫ UPLOAD
            <input type="file" id="fm-upload" multiple style="display:none" onchange="fmUpload(this.files)">
        </label>
    </div>
</div>

<div class="panel">
    <div class="panel-title" style="display:flex;align-items:center;gap:8px;">
        <span id="fm-breadcrumbs"></span>
    </div>
    <div id="fm-content" style="min-height:200px;">
        <div class="text-dim" style="padding:20px;text-align:center;">Loading...</div>
    </div>
</div>

<style>
.fm-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; border-bottom: 1px dashed var(--border);
    transition: background 0.1s;
}
.fm-item:hover { background: var(--accent-soft); }
.fm-item .fm-icon { font-size: 18px; width: 24px; text-align: center; flex-shrink: 0; }
.fm-item .fm-name { flex: 1; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fm-item .fm-name:hover { color: var(--accent); text-decoration: underline; }
.fm-item .fm-meta { color: var(--text-dim); font-size: 11px; min-width: 70px; text-align: right; flex-shrink: 0; }
.fm-item .fm-actions { display: flex; gap: 4px; flex-shrink: 0; }
.fm-item .fm-actions button { padding: 3px 8px; font-size: 10px; }
.fm-empty { color: var(--text-dim); padding: 20px; text-align: center; }
</style>

<script>
(function () {
  let currentFolder = '/';

  function load(folder) {
    folder = folder || '/';
    currentFolder = folder;
    csrfFetch('/api/files/list?folder=' + encodeURIComponent(folder))
      .then(r => r.json())
      .then(render)
      .catch(e => {
        document.getElementById('fm-content').innerHTML = '<div class="fm-empty">Error: ' + e.message + '</div>';
      });
  }

  function render(data) {
    const bc = document.getElementById('fm-breadcrumbs');
    bc.innerHTML = (data.breadcrumbs || []).map((b, i, arr) =>
      i < arr.length - 1
        ? '<a href="#" onclick="event.preventDefault(); fmNav(\'' + esc(b.path) + '\')" style="color:var(--text-dim)">' + esc(b.name) + '</a> <span style="color:var(--border)">/</span>'
        : '<span style="color:var(--accent)">' + esc(b.name) + '</span>'
    ).join(' ');

    const el = document.getElementById('fm-content');
    let html = '';

    if (data.parent !== null && data.parent !== undefined) {
      html += '<div class="fm-item"><span class="fm-icon">📁</span><span class="fm-name" onclick="fmNav(\'' + esc(data.parent) + '\')">..</span><span class="fm-meta"></span><span class="fm-actions"></span></div>';
    }

    for (const f of data.folders || []) {
      html += '<div class="fm-item">'
        + '<span class="fm-icon">📁</span>'
        + '<span class="fm-name" onclick="fmNav(\'' + esc(f.path) + '\')">' + esc(f.name) + '</span>'
        + '<span class="fm-meta"></span>'
        + '<span class="fm-actions">'
        + '<button class="ghost" onclick="fmRenameFolder(\'' + esc(f.path) + '\',\'' + esc(f.name) + '\')">REN</button>'
        + '<button class="ghost danger" onclick="fmDeleteFolder(\'' + esc(f.path) + '\')">DEL</button>'
        + '</span></div>';
    }

    for (const f of data.files || []) {
      const sz = f.size_bytes < 1024 ? f.size_bytes + ' B' : f.size_bytes < 1048576 ? (f.size_bytes/1024).toFixed(1)+' KB' : (f.size_bytes/1048576).toFixed(1)+' MB';
      html += '<div class="fm-item">'
        + '<span class="fm-icon">📄</span>'
        + '<span class="fm-name">' + esc(f.original_name) + '</span>'
        + '<span class="fm-meta">' + sz + '</span>'
        + '<span class="fm-actions">'
        + '<button class="ghost" onclick="fmRenameFile(' + f.id + ',\'' + esc(f.original_name) + '\')">REN</button>'
        + '<button class="ghost" onclick="fmMoveFile(' + f.id + ')">MOVE</button>'
        + '<button class="ghost danger" onclick="fmDeleteFile(' + f.id + ',\'' + esc(f.original_name) + '\')">DEL</button>'
        + '</span></div>';
    }

    if (!(data.folders || []).length && !(data.files || []).length) {
      html += '<div class="fm-empty">' + (data.parent === null ? 'No files yet. Upload some or create a folder.' : 'Empty folder.') + '</div>';
    }
    el.innerHTML = html;
  }

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  window.fmNav = function(f) { load(f); };

  window.fmNewFolder = function() {
    const name = prompt('Folder name:');
    if (!name) return;
    csrfFetch('/api/files/folder', { method:'POST', body: JSON.stringify({ parent: currentFolder, name }) })
      .then(r=>r.json()).then(d => { if(d.ok) load(currentFolder); else toast(d.error||'failed','danger'); });
  };

  window.fmUpload = function(fileList) {
    if (!fileList || !fileList.length) return;
    const meta = document.querySelector('meta[name="csrf-token"]');
    const promises = [];
    for (const file of fileList) {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('folder', currentFolder);
      promises.push(fetch('/api/files/upload', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': meta?.content || '', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd,
      }).then(r => r.json()));
    }
    Promise.all(promises).then(() => { toast(fileList.length + ' file(s) uploaded'); load(currentFolder); });
    document.getElementById('fm-upload').value = '';
  };

  window.fmRenameFile = function(id, current) {
    const name = prompt('New name:', current);
    if (!name || name === current) return;
    csrfFetch('/api/files/' + id + '/rename', { method: 'PATCH', body: JSON.stringify({ name }) })
      .then(r => r.json()).then(() => load(currentFolder));
  };

  window.fmMoveFile = function(id) {
    const folder = prompt('Move to folder path:', currentFolder);
    if (!folder) return;
    csrfFetch('/api/files/' + id + '/move', { method: 'PATCH', body: JSON.stringify({ folder }) })
      .then(r => r.json()).then(() => { toast('Moved'); load(currentFolder); });
  };

  window.fmDeleteFile = function(id, name) {
    if (!confirm('Delete ' + name + '?')) return;
    csrfFetch('/api/files/' + id, { method: 'DELETE' })
      .then(r => r.json()).then(() => load(currentFolder));
  };

  window.fmRenameFolder = function(path, current) {
    const name = prompt('Rename folder:', current);
    if (!name || name === current) return;
    csrfFetch('/api/files/folder/rename', { method: 'POST', body: JSON.stringify({ old_path: path, new_name: name }) })
      .then(r => r.json()).then(() => load(currentFolder));
  };

  window.fmDeleteFolder = function(path) {
    if (!confirm('Delete folder ' + path + ' and all its contents?')) return;
    csrfFetch('/api/files/folder/delete', { method: 'POST', body: JSON.stringify({ path }) })
      .then(r => r.json()).then(() => load(currentFolder));
  };

  document.addEventListener('DOMContentLoaded', function() { load('/'); });
})();
</script>
@endsection
