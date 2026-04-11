// CodeMirror 6 Python editor for transformations.
// Loaded from CDN as ES modules. We just wire it up to our textareas.

import { EditorView, basicSetup } from "https://esm.sh/codemirror@6.0.1";
import { python } from "https://esm.sh/@codemirror/lang-python@6.1.6";
import { oneDark } from "https://esm.sh/@codemirror/theme-one-dark@6.1.2";
import { keymap } from "https://esm.sh/@codemirror/view@6.26.3";

const ta = document.getElementById('source-textarea');
if (ta) {
  const parent = document.getElementById('editor-mount');
  const view = new EditorView({
    doc: ta.value,
    extensions: [
      basicSetup,
      python(),
      oneDark,
      EditorView.theme({
        '&': { height: '100%' },
        '.cm-scroller': { fontFamily: 'JetBrains Mono, Consolas, monospace' },
      }),
      EditorView.updateListener.of((v) => {
        if (v.docChanged) {
          ta.value = v.state.doc.toString();
          setStatus('modified');
        }
      }),
      keymap.of([
        {
          key: 'Ctrl-s',
          run: () => { document.getElementById('save-btn')?.click(); return true; },
        },
      ]),
    ],
    parent: parent,
  });
  ta.style.display = 'none';

  window.editorSave = function (url) {
    setStatus('saving...');
    window.csrfFetch(url, { method: 'PUT', body: JSON.stringify({ source: ta.value }) })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) { setStatus('saved'); window.toast('Saved'); }
        else { setStatus('error'); window.toast('Error: ' + (data.error || 'unknown'), 'danger'); }
      })
      .catch((e) => { setStatus('error'); window.toast('Network: ' + e.message, 'danger'); });
  };

  window.editorValidate = function () {
    window.csrfFetch('/api/transformations/validate', { method: 'POST', body: JSON.stringify({ source: ta.value }) })
      .then((r) => r.json())
      .then((data) => {
        const v = data.data || data;
        if (v.valid) { setStatus('syntax ok'); window.toast('Syntax OK'); }
        else { setStatus('syntax error'); window.toast(v.error || 'Syntax error', 'danger'); }
      });
  };

  window.editorReload = function () {
    window.csrfFetch('/api/transformations/reload', { method: 'POST' })
      .then((r) => r.json())
      .then(() => window.toast('Engine reloaded'));
  };
}

function setStatus(s) {
  const el = document.getElementById('editor-status');
  if (el) el.textContent = s;
}
