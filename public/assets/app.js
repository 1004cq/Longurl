(() => {
  const form = document.getElementById('gen-form');
  const body = document.body;
  if (!form) return;
  const $ = (id) => document.getElementById(id);
  const url = $('url'), length = $('length'), range = $('length-range');
  const lenLabel = $('len-label'), preview = $('preview'), err = $('err');
  const result = $('result'), outUrl = $('out-url'), outLen = $('out-len'), outTarget = $('out-target');
  const copyBtn = $('copy'), openBtn = $('open'), submit = $('submit'), pasteBtn = $('paste');
  const ticks = document.querySelectorAll('[data-len]');
  const base = body.dataset.base || window.location.origin;
  const labels = { generate: body.dataset.generate || 'Generate long URL', generating: body.dataset.generating || 'Generating…', copy: body.dataset.copy || 'Copy', copied: body.dataset.copied || 'Copied' };
  const min = parseInt(length.min || range.min || '8', 10), max = parseInt(length.max || range.max || '5000', 10);
  const clamp = (n) => Math.min(max, Math.max(min, n));
  document.documentElement.classList.add('js-ready');
  window.requestAnimationFrame(() => body.classList.add('is-ready'));
  const normalize = (v) => { v = (v || '').trim(); if (!v) return ''; return /^[a-z][a-z0-9+.-]*:\/\//i.test(v) ? v : `https://${v}`; };
  const setLength = (raw) => {
    const n = clamp(parseInt(String(raw), 10) || min);
    length.value = String(n); range.value = String(n); lenLabel.innerHTML = `${n} <small>e</small>`;
    ticks.forEach((x) => x.classList.toggle('active', x.dataset.len === String(n)));
    const ratio = ((n - min) / Math.max(1, max - min)) * 100;
    range.style.setProperty('--range-progress', `${ratio}%`);
    lenLabel.classList.remove('bump');
    window.requestAnimationFrame(() => lenLabel.classList.add('bump'));
    preview.textContent = `${base}/` + 'e'.repeat(Math.min(n, 42)) + (n > 42 ? '…' : '') + `  ·  ${n} e`;
  };
  const updateUrl = () => {
    const value = url.value.trim();
    $('url-count').textContent = value.length;
    $('url-state').textContent = value ? (normalize(value).startsWith('https://') && !/^https:\/\//i.test(value) ? 'https:// will be added automatically' : 'Ready to transform') : 'https:// will be added automatically';
    url.closest('.url-field').classList.toggle('has-value', !!value);
    url.closest('.url-field').classList.toggle('is-valid', /^https?:\/\/[^\s]+$/i.test(normalize(value)));
  };
  ticks.forEach((tick) => tick.addEventListener('click', () => {
    setLength(tick.dataset.len);
    tick.classList.remove('tap');
    window.requestAnimationFrame(() => tick.classList.add('tap'));
  }));
  range.addEventListener('input', () => setLength(range.value), { passive: true });
  length.addEventListener('input', () => { if (length.value !== '') setLength(length.value); });
  length.addEventListener('change', () => setLength(length.value));
  url.addEventListener('input', updateUrl);
  url.addEventListener('keydown', (event) => { if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') form.requestSubmit(); });
  pasteBtn?.addEventListener('click', async () => { try { url.value = await navigator.clipboard.readText(); updateUrl(); url.focus(); } catch { url.focus(); } });
  setLength(length.value || '100'); updateUrl();

  form.addEventListener('submit', async (event) => {
    event.preventDefault(); err.textContent = ''; result.classList.remove('show');
    if (!url.value.trim()) { err.textContent = 'Please enter a URL.'; url.focus(); return; }
    submit.disabled = true; submit.classList.add('is-loading'); submit.querySelector('.btn-icon').textContent = '…'; submit.querySelector('.btn-label').textContent = labels.generating;
    try {
      const fd = new FormData(form); fd.set('url', normalize(url.value)); fd.set('length', String(clamp(parseInt(length.value, 10) || min)));
      const res = await fetch('/?action=create', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json(); if (!data.success) throw new Error(data.error || 'Failed');
      outUrl.textContent = data.url; outLen.textContent = data.length + ' e'; outTarget.textContent = data.target; openBtn.href = data.url;
      result.classList.remove('show');
      window.requestAnimationFrame(() => result.classList.add('show'));
      result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (ex) { err.textContent = ex instanceof Error ? ex.message : 'Failed'; }
    finally { submit.disabled = false; submit.classList.remove('is-loading'); submit.querySelector('.btn-icon').textContent = '↗'; submit.querySelector('.btn-label').textContent = labels.generate; }
  });
  copyBtn?.addEventListener('click', async () => { try { await navigator.clipboard.writeText(outUrl.textContent); copyBtn.classList.add('copied'); copyBtn.querySelector('span').textContent = '✓'; copyBtn.querySelector('.btn-label').textContent = labels.copied; setTimeout(() => { copyBtn.classList.remove('copied'); copyBtn.querySelector('span').textContent = '⧉'; copyBtn.querySelector('.btn-label').textContent = labels.copy; }, 1600); } catch {} });
})();
