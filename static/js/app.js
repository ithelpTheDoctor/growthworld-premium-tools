(function () {
  const base = window.GW_BASE || '';
  const toUrl = (path) => `${base}${path}`;

  // external links open new tab
  document.querySelectorAll('a[href^="http"]').forEach((a) => {
    try {
      const u = new URL(a.href, location.href);
      if (u.host !== location.host) {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
      }
    } catch (_) {}
  });

  // theme
  const root = document.documentElement;
  const toggle = document.getElementById('theme-toggle');
  const savedTheme = localStorage.getItem('gw_theme');
  const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  root.setAttribute('data-theme', savedTheme || (systemDark ? 'dark' : 'light'));
  if (toggle) {
    const setLabel = () => {
      const dark = root.getAttribute('data-theme') === 'dark';
      toggle.textContent = dark ? '☀️' : '🌙';
      toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    };
    setLabel();
    toggle.addEventListener('click', () => {
      const next = (root.getAttribute('data-theme') || 'light') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('gw_theme', next);
      setLabel();
    });
  }

  // mobile menu popup behavior
  const menuToggle = document.getElementById('menu-toggle');
  const nav = document.getElementById('main-nav');
  const backdrop = document.getElementById('menu-backdrop');
  const menuClose = document.getElementById('menu-close');
  const closeMenu = () => { nav?.classList.remove('open'); backdrop?.classList.remove('open'); };
  const openMenu = () => { nav?.classList.add('open'); backdrop?.classList.add('open'); };
  if (menuToggle && nav) {
    menuToggle.addEventListener('click', () => nav.classList.contains('open') ? closeMenu() : openMenu());
    menuClose?.addEventListener('click', closeMenu);
    backdrop?.addEventListener('click', closeMenu);
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
    document.addEventListener('click', (e) => {
      if (!nav.classList.contains('open')) return;
      if (!nav.contains(e.target) && e.target !== menuToggle) closeMenu();
    });
  }

  const reviewForm = document.getElementById('review-form');
  if (reviewForm) {
    reviewForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const box = document.getElementById('review-errors');
      const data = new FormData(reviewForm);
      const res = await fetch(toUrl('/api/review/create'), { method: 'POST', body: data });
      const json = await res.json();
      if (!json.ok) {
        box.innerHTML = '<ul class="list-clean">' + (json.errors || ['Unable to submit']).map(x => `<li>${x}</li>`).join('') + '</ul>';
        return;
      }
      box.innerHTML = '<p class="ok">Thanks! Your review is submitted for admin approval.</p>';
    });
  }

  document.querySelectorAll('.review-action').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      btn.disabled = true;
      const csrf = document.getElementById('admin-review-csrf')?.value || '';
      const msg = document.getElementById('review-admin-msg');
      const body = new FormData();
      body.append('csrf', csrf);
      body.append('review_id', btn.dataset.id || '0');
      body.append('action', btn.dataset.action || '');
      const res = await fetch(toUrl('/api/review/moderate'), { method: 'POST', body });
      const json = await res.json();
      if (!json.ok) {
        msg.innerHTML = '<p class="notice">Failed to update review.</p>';
        btn.disabled = false;
        return;
      }
      msg.innerHTML = '<p class="ok">Review updated.</p>';
      const row = btn.closest('tr, li');
      const action = btn.dataset.action;
      if (row && (action === 'approve' || action === 'reject')) row.remove();
      if (row && action === 'favorite') {
        btn.textContent = (json.row && Number(json.row.is_favorite) === 1) ? 'Unfavorite' : 'Favorite';
      }
      btn.disabled = false;
    });
  });

  const form = document.getElementById('service-form');
  if (!form) return;

  const errorsBox = document.getElementById('admin-errors');
  const typeSel = form.querySelector('[name="service_type"]');
  const wrapFeature = document.getElementById('features-wrap');
  const wrapIns = document.getElementById('instructions-wrap');
  const addFeature = document.getElementById('add-feature');
  const addIns = document.getElementById('add-ins');
  const rowDownload = document.getElementById('download-row');
  const rowExt = document.getElementById('extension-row');
  const rowHtml = document.getElementById('html-row');
  const submitBtn = document.getElementById('service-submit');

  const xorKey = atob(window.GW_XOR_OBF || '').split('').reverse().join('');
  const xor = (text) => {
    let out = '';
    for (let i = 0; i < text.length; i++) out += String.fromCharCode(text.charCodeAt(i) ^ xorKey.charCodeAt(i % xorKey.length));
    return btoa(out);
  };

  const updateByType = () => {
    const t = typeSel.value;
    rowDownload.style.display = t === 'windows' ? 'block' : 'none';
    rowExt.style.display = t === 'extension' ? 'block' : 'none';
    rowHtml.style.display = t === 'browser' ? 'block' : 'none';
  };
  typeSel.addEventListener('change', updateByType);
  updateByType();

  function addDynamicInput(parent, name, max) {
    const line = document.createElement('div');
    line.className = 'inline-item';
    line.innerHTML = `<button type="button" class="x">×</button><input name="${name}" maxlength="${max}">`;
    line.querySelector('.x').onclick = () => line.remove();
    parent.appendChild(line);
  }
  addFeature.onclick = () => addDynamicInput(wrapFeature, 'features[]', 160);
  addIns.onclick = () => addDynamicInput(wrapIns, 'instructions[]', 255);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const obj = Object.fromEntries(data.entries());
    obj.features = data.getAll('features[]').filter(Boolean);
    obj.instructions = data.getAll('instructions[]').filter(Boolean);

    const errors = [];
    if ((obj.title || '').length < 20) errors.push('Service Title must be at least 20 characters.');
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(obj.slug || '')) errors.push('Service slug must only use a-z, 0-9 and dashes.');
    if ((obj.seo_description || '').length < 110 || (obj.seo_description || '').length > 160) errors.push('SEO description must be 110-160 characters.');
    if ((obj.long_description || '').length < 200 || (obj.long_description || '').length > 1000) errors.push('Long description must be 200-1000 characters.');
    if (!obj.features.length) errors.push('At least one feature is required.');
    if (obj.service_type === 'windows' && !(obj.download_url || '').startsWith('http')) errors.push('Executable download URL is required.');
    if (obj.service_type === 'extension' && !(obj.extension_url || '').startsWith('http')) errors.push('Extension URL is required.');
    if ((obj.demo_tutorial_url || '').trim() && !(obj.demo_tutorial_url || '').startsWith('http')) errors.push('Service Demo Tutorial URL must be valid.');

    if (errors.length) {
      errorsBox.innerHTML = '<ul class="list-clean">' + errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    }

    const body = new FormData();
    body.append('csrf', form.querySelector('[name="csrf"]').value);
    body.append('payload', xor(JSON.stringify(obj)));
    const fileField = form.querySelector('[name="feature_image"]');
    if (fileField && fileField.files && fileField.files[0]) body.append('feature_image', fileField.files[0]);

    const res = await fetch(toUrl('/api/service/create'), { method: 'POST', body });
    const json = await res.json();
    if (!json.ok) {
      errorsBox.innerHTML = '<ul class="list-clean">' + json.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save service';
      }
      return;
    }

    form.style.display = 'none';
    errorsBox.innerHTML = '<p class="ok">Service saved successfully.</p><p><a class="btn btn-primary" href="' + toUrl('/admin?tab=create') + '">Create new service</a></p>';
  });
})();
