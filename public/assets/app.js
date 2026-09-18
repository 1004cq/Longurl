(() => {
  const form = document.getElementById('gen-form');
  if (!form) return;

  const url = document.getElementById('url');
  const length = document.getElementById('length');
  const preview = document.getElementById('preview');
  const err = document.getElementById('err');
  const result = document.getElementById('result');
  const outUrl = document.getElementById('out-url');
  const outLen = document.getElementById('out-len');
  const outTarget = document.getElementById('out-target');
  const copyBtn = document.getElementById('copy');
  const openBtn = document.getElementById('open');
  const submit = document.getElementById('submit');
  const pills = document.querySelectorAll('[data-len]');
  const base = document.body.dataset.base || '';
  const labels = {
    generate: document.body.dataset.generate || 'Generate long URL',
    generating: document.body.dataset.generating || 'Generating…',
    copy: document.body.dataset.copy || 'Copy',
    copied: document.body.dataset.copied || 'Copied',
  };

  const normalize = (v) => {
    v = (v || '').trim();
    if (!v) return '';
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(v)) v = 'https://' + v;
    return v;
  };

  const renderPreview = () => {
    const n = Math.max(0, parseInt(length.value || '0', 10) || 0);
    preview.textContent = `${base}/` + 'e'.repeat(Math.min(n, 36)) + (n > 36 ? '…' : '') + `  ·  ${n} e`;
  };

  pills.forEach((p) => {
    p.addEventListener('click', () => {
      pills.forEach((x) => x.classList.remove('active'));
      p.classList.add('active');
      length.value = p.dataset.len;
      renderPreview();
    });
  });

  length.addEventListener('input', () => {
    pills.forEach((x) => x.classList.toggle('active', x.dataset.len === length.value));
    renderPreview();
  });

  renderPreview();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.textContent = '';
    result.classList.remove('show');
    submit.disabled = true;
    submit.textContent = labels.generating;

    try {
      const fd = new FormData(form);
      fd.set('url', normalize(url.value));

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
