// Common bootstrap for every page.

(function () {
  // ---- theme ----
  const STORAGE_KEY = 'osint.theme';
  const stored = localStorage.getItem(STORAGE_KEY);
  const initial = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  document.documentElement.setAttribute('data-theme', initial);

  window.toggleTheme = function () {
    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem(STORAGE_KEY, next);
    const btn = document.querySelector('.theme-toggle');
    if (btn) btn.textContent = next === 'dark' ? '☀ LIGHT' : '☾ DARK';
  };

  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.theme-toggle');
    if (btn) {
      btn.textContent = (document.documentElement.getAttribute('data-theme') === 'dark') ? '☀ LIGHT' : '☾ DARK';
      btn.addEventListener('click', window.toggleTheme);
    }

    // eye-logo pupil follows cursor
    const pupils = document.querySelectorAll('.eye-pupil');
    if (pupils.length) {
      let tick = false;
      document.addEventListener('mousemove', (e) => {
        if (tick) return;
        tick = true;
        requestAnimationFrame(() => {
          pupils.forEach((p) => {
            const svg = p.closest('svg');
            if (!svg) return;
            const r = svg.getBoundingClientRect();
            const cx = r.left + r.width / 2;
            const cy = r.top + r.height / 2;
            const dx = Math.max(-1, Math.min(1, (e.clientX - cx) / 160));
            const dy = Math.max(-1, Math.min(1, (e.clientY - cy) / 160));
            p.setAttribute('transform', `translate(${dx * 12}, ${dy * 5})`);
          });
          tick = false;
        });
      });
      // periodic blink
      setInterval(() => {
        pupils.forEach((p) => {
          const svg = p.closest('svg');
          if (!svg) return;
          svg.classList.add('blink');
          setTimeout(() => svg.classList.remove('blink'), 150);
        });
      }, 6500);
    }

    // confirm delete forms
    document.querySelectorAll('form[data-confirm]').forEach((f) => {
      f.addEventListener('submit', (e) => {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
      });
    });
  });

  // ---- csrf helper ----
  window.csrfFetch = function (url, opts = {}) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta ? meta.getAttribute('content') : '';
    opts.headers = Object.assign(
      { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
      opts.headers || {}
    );
    return fetch(url, opts);
  };

  window.toast = function (msg, kind = 'success') {
    const el = document.createElement('div');
    el.className = 'alert ' + (kind || '');
    el.textContent = msg;
    el.style.position = 'fixed';
    el.style.right = '20px';
    el.style.bottom = '20px';
    el.style.zIndex = '9999';
    el.style.minWidth = '240px';
    el.style.boxShadow = 'var(--shadow)';
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; }, 2600);
    setTimeout(() => { el.remove(); }, 3100);
  };
})();
