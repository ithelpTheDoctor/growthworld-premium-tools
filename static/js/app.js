(function () {
  const base = window.GW_BASE || '';
  const toUrl = (path) => `${base}${path}`;
  const root = document.documentElement;
  const toggle = document.getElementById('theme-toggle');
  const savedTheme = localStorage.getItem('gw_theme');
  const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  root.setAttribute('data-theme', savedTheme || (systemDark ? 'dark' : 'light'));

  if (toggle) {
    const setLabel = () => { toggle.textContent = root.getAttribute('data-theme') === 'dark' ? 'Dark' : 'Light'; };
    setLabel();
    toggle.addEventListener('click', () => {
      const next = (root.getAttribute('data-theme') || 'light') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('gw_theme', next);
      setLabel();
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
      const csrf = document.getElementById('admin-review-csrf')?.value || '';
      const msg = document.getElementById('review-admin-msg');
      const body = new FormData();
      body.append('csrf', csrf);
      body.append('review_id', btn.dataset.id || '0');
      body.append('action', btn.dataset.action || '');
      const res = await fetch(toUrl('/api/review/moderate'), { method: 'POST', body });
      const json = await res.json();
      msg.innerHTML = json.ok ? '<p class="ok">Review updated. Refreshing...</p>' : '<p class="notice">Failed to update review.</p>';
      if (json.ok) setTimeout(() => location.reload(), 600);
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

    const body = new FormData();
    body.append('csrf', form.querySelector('[name="csrf"]').value);
    body.append('payload', xor(JSON.stringify(obj)));
    const res = await fetch(toUrl('/api/service/create'), { method: 'POST', body });
    const json = await res.json();
    if (!json.ok) {
      errorsBox.innerHTML = '<ul class="list-clean">' + json.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
      return;
    }
    errorsBox.innerHTML = '<p class="ok">Service saved successfully.</p>';
  });
})();
