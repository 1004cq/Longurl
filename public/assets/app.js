(() => {
  const form = document.getElementById('gen-form');
  if (!form) return;

  const url = document.getElementById('url');
  const length = document.getElementById('length');
  const range = document.getElementById('length-range');
  const lenLabel = document.getElementById('len-label');
  const preview = document.getElementById('preview');
  const err = document.getElementById('err');
  const result = document.getElementById('result');
  const outUrl = document.getElementById('out-url');
  const outLen = document.getElementById('out-len');
  const outTarget = document.getElementById('out-target');
  const copyBtn = document.getElementById('copy');
  const openBtn = document.getElementById('open');
  const submit = document.getElementById('submit');
  const ticks = document.querySelectorAll('[data-len]');
  const base = document.body.dataset.base || '';
  const labels = {
    generate: document.body.dataset.generate || 'Generate long URL',
    generating: document.body.dataset.generating || 'Generating…',
    copy: document.body.dataset.copy || 'Copy',
    copied: document.body.dataset.copied || 'Copied',
  };

  const min = parseInt(length?.min || range?.min || '8', 10);
  const max = parseInt(length?.max || range?.max || '5000', 10);

  const clamp = (n) => Math.min(max, Math.max(min, n));

  const normalize = (v) => {
    v = (v || '').trim();
    if (!v) return '';
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(v)) v = 'https://' + v;
    return v;
  };

  const setLength = (raw) => {
    const n = clamp(parseInt(String(raw), 10) || min);
    length.value = String(n);
    if (range) range.value = String(n);
    if (lenLabel) lenLabel.textContent = n + ' e';
    ticks.forEach((x) => x.classList.toggle('active', x.dataset.len === String(n)));
    if (preview) {
      preview.textContent = `${base}/` + 'e'.repeat(Math.min(n, 36)) + (n > 36 ? '…' : '') + `  ·  ${n} e`;
    }
  };

  ticks.forEach((p) => {
    p.addEventListener('click', () => setLength(p.dataset.len));
  });

  range?.addEventListener('input', () => setLength(range.value));
  length.addEventListener('input', () => setLength(length.value));
  setLength(length.value || '100');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';
    result.classList.remove('show');
    submit.disabled = true;
    submit.textContent = labels.generating;

    try {
      const fd = new FormData(form);
      fd.set('url', normalize(url.value));
      fd.set('length', String(clamp(parseInt(length.value, 10) || min)));

      const res = await fetch('/?action=create', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'fetch' },
      });

      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed');

      outUrl.textContent = data.url;
      outLen.textContent = data.length + ' e';
      outTarget.textContent = data.target;
      openBtn.href = data.url;
      result.classList.add('show');
    } catch (ex) {
      err.textContent = ex instanceof Error ? ex.message : 'Failed';
    } finally {
      submit.disabled = false;
      submit.textContent = labels.generate;
    }
  });

  copyBtn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(outUrl.textContent);
      copyBtn.textContent = labels.copied;
      setTimeout(() => (copyBtn.textContent = labels.copy), 1200);
    } catch {
      copyBtn.textContent = labels.copy;
    }
  });
})();
