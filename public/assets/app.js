(() => {
  const { createApp, ref, computed, onMounted, nextTick } = window.Vue;
  const root = document.getElementById('app');
  const body = document.body;
  if (!root || !window.Vue) return;
  const copy = window.EEEE_COPY || {};
  const isLost = body.dataset.page === 'lost';
  const min = Number(body.dataset.minLength || 8), max = Number(body.dataset.maxLength || 2200);
  const base = body.dataset.base || window.location.origin;
  const lang = document.documentElement.lang || 'en';
  const clamp = (n) => Math.min(max, Math.max(min, Number(n) || min));
  const bare = (value) => String(value || '').replace(/^https?:\/\//i, '');
  const stripTrail = (value) => String(value || '').replace(/[\s.,;:!?)]}+\u3002\uFF0C\u3001\uFF01\uFF1F\"\'\]>\u300d\u300f]+$/g, '');
  const extractUrl = (raw) => {
    const text = String(raw || '').trim();
    if (!text) return '';
    const tagged = text.match(/https?:\/\/[^\s<>\"']+/i);
    if (tagged) return stripTrail(tagged[0]);
    const www = text.match(/\bwww\.[^\s<>\"']+/i);
    if (www) return stripTrail(www[0]);
    const host = text.match(/\b(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s<>\"']*)?/i);
    if (host && /[a-z]/i.test(host[0])) return stripTrail(host[0]);
    return '';
  };
  const normalize = (value) => {
    const extracted = extractUrl(value);
    if (!extracted) return '';
    return /^[a-z][a-z0-9+.-]*:\/\//i.test(extracted) ? extracted : `https://${extracted}`;
  };
  const buildEQr = (text) => {
    if (typeof qrcode !== 'function') return { ok: false };
    const payload = String(text || '');
    if (!payload || payload.length > 2400) return { ok: false };
    try {
      const qr = qrcode(0, 'L');
      qr.addData(payload);
      qr.make();
      const n = qr.getModuleCount();
      const quiet = 4;
      const modules = [];
      for (let y = -quiet; y < n + quiet; y++) {
        const row = [];
        for (let x = -quiet; x < n + quiet; x++) {
          row.push(x >= 0 && y >= 0 && x < n && y < n && qr.isDark(y, x));
        }
        modules.push(row);
      }
      return { ok: true, modules, size: n, gridSize: n + quiet * 2 };
    } catch {
      return { ok: false };
    }
  };
  const langUrl = (next) => `${window.location.pathname}?lang=${next}`;

  let touchStartY = 0;
  let touchOnControl = false;
  const isInteractiveTouch = (target) => {
    if (!(target instanceof Element)) return false;
    return Boolean(target.closest('input, textarea, select, button, a, .len-slider, [role="slider"]'));
  };
  document.addEventListener('touchstart', (event) => {
    if (event.touches.length !== 1) return;
    touchStartY = event.touches[0].clientY;
    touchOnControl = isInteractiveTouch(event.target);
  }, { passive: true });
  document.addEventListener('touchmove', (event) => {
    if (event.touches.length !== 1 || touchOnControl) return;
    const y = event.touches[0].clientY;
    const delta = y - touchStartY;
    const rootEl = document.scrollingElement || document.documentElement;
    const maxScroll = Math.max(0, rootEl.scrollHeight - rootEl.clientHeight);
    const atTop = rootEl.scrollTop <= 0;
    const atBottom = rootEl.scrollTop >= maxScroll - 1;
    if ((atTop && delta > 0) || (atBottom && delta < 0)) event.preventDefault();
  }, { passive: false });

  createApp({
    setup() {
      const url = ref(''), length = ref(100), loading = ref(false), error = ref(''), result = ref(null), copied = ref(false);
      const preview = computed(() => bare(base) + '/' + 'e'.repeat(Math.min(length.value, 42)) + (length.value > 42 ? '…' : '') + `  ·  ${length.value} e`);
      const displayUrl = computed(() => result.value ? bare(result.value.url) : '');
      const qr = computed(() => result.value ? buildEQr(result.value.url) : { ok: false });
      const ratio = computed(() => `${((length.value - min) / Math.max(1, max - min)) * 100}%`);
      const lengthTicks = [50, 100, 200, 500, 1000, 2000].filter((n) => n >= min && n <= max);
      const rangeEl = ref(null);
      const toLength = (value) => Math.round(clamp(value));
      const progressFor = (value) => `${((toLength(value) - min) / Math.max(1, max - min)) * 100}%`;
      let lightFrame = 0;
      let pendingLength = null;
      let pointerId = null;
      let lastSceneValue;
      const applyScene = (value) => {
        if (lastSceneValue === value) return;
        lastSceneValue = value;
        window.EEEEScene?.setLength(value);
      };
      const cancelLight = () => {
        if (lightFrame) cancelAnimationFrame(lightFrame);
        lightFrame = 0;
        pendingLength = null;
      };
      const rangeWrap = () => rangeEl.value?.closest('.len-wrap');
      const setDragging = (on) => { rangeWrap()?.classList.toggle('is-dragging', on); };
      const syncRange = (value) => {
        const el = rangeEl.value;
        if (el && el.value !== String(value)) el.value = String(value);
      };
      const paintLight = (value) => {
        const n = toLength(value);
        const valueEl = document.getElementById('len-value');
        if (valueEl) valueEl.textContent = String(n);
        const number = document.querySelector('.number-field input');
        if (number && number.value !== String(n)) number.value = String(n);
        rangeWrap()?.style.setProperty('--range-progress', progressFor(n));
      };
      const scheduleLight = (value) => {
        pendingLength = toLength(value);
        if (lightFrame) return;
        lightFrame = requestAnimationFrame(() => {
          lightFrame = 0;
          if (pendingLength == null) return;
          paintLight(pendingLength);
        });
      };
      const setLength = (value) => {
        cancelLight();
        pointerId = null;
        setDragging(false);
        length.value = toLength(value);
        paintLight(length.value);
        syncRange(length.value);
        applyScene(length.value);
      };
      const valueFromClientX = (clientX) => {
        const el = rangeEl.value;
        if (!el) return length.value;
        const rect = el.getBoundingClientRect();
        const thumb = Math.min(28, rect.width / 8);
        const span = Math.max(1, rect.width - thumb);
        const t = Math.min(1, Math.max(0, (clientX - rect.left - thumb / 2) / span));
        return toLength(min + t * (max - min));
      };
      const applyPointer = (event) => {
        const next = valueFromClientX(event.clientX);
        syncRange(next);
        scheduleLight(next);
      };
      const onSliderPointerDown = (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        if (event.target instanceof HTMLButtonElement || event.target?.closest?.('.len-tick, .number-field')) return;
        pointerId = event.pointerId;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        setDragging(true);
        applyPointer(event);
      };
      const onSliderPointerMove = (event) => {
        if (pointerId !== event.pointerId) return;
        applyPointer(event);
      };
      const onSliderPointerUp = (event) => {
        if (pointerId != null && event.pointerId !== pointerId) return;
        const next = pointerId != null ? valueFromClientX(event.clientX) : (pendingLength ?? length.value);
        pointerId = null;
        setLength(next);
      };
      const onRangeInput = (event) => {
        if (pointerId != null) return;
        setDragging(true);
        syncRange(event.target.value);
        scheduleLight(event.target.value);
      };
      const onRangeCommit = (event) => {
        if (pointerId != null) return;
        setLength(event?.target?.value ?? pendingLength ?? length.value);
      };
      const cleanField = () => {
        const clean = extractUrl(url.value);
        if (clean && clean !== url.value.trim()) {
          url.value = clean;
          error.value = '';
          return true;
        }
        if (url.value.trim() && !clean) {
          error.value = lang.startsWith('zh') ? '请只粘贴纯链接，先去掉多余文字' : 'Paste a clean URL only.';
          return false;
        }
        error.value = '';
        return true;
      };
      const updateUrl = () => { cleanField(); };
      const submit = async () => {
        error.value = ''; copied.value = false;
        cleanField();
        const target = normalize(url.value);
        if (!target) { error.value = lang.startsWith('zh') ? '请输入纯链接' : 'Please enter a URL.'; return; }
        loading.value = true;
        try {
          const fd = new FormData(); fd.set('url', target); fd.set('length', String(length.value));
          const response = await fetch('/api/create', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
          const data = await response.json(); if (!data.success) throw new Error(data.error || 'Failed');
          result.value = data; window.EEEEScene?.success(data.length); await nextTick(); document.querySelector('.result')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) { error.value = e instanceof Error ? e.message : 'Failed'; }
        finally { loading.value = false; }
      };
      const paste = async () => {
        try {
          const raw = await navigator.clipboard.readText();
          url.value = extractUrl(raw) || raw.trim();
          cleanField();
        } catch {}
      };
      const onPaste = (event) => {
        const raw = event.clipboardData?.getData('text') || '';
        const clean = extractUrl(raw);
        if (!clean) return;
        event.preventDefault();
        url.value = clean;
        error.value = '';
      };
      const copyResult = async () => {
        if (!result.value) return;
        try {
          await navigator.clipboard.writeText(bare(result.value.url));
          copied.value = true;
          setTimeout(() => copied.value = false, 1600);
        } catch {}
      };
      onMounted(() => { setLength(100); if (isLost) window.EEEEScene?.lost(); document.documentElement.classList.add('js-ready'); requestAnimationFrame(() => body.classList.add('is-ready')); });
      return { copy, lang, langUrl, isLost, min, max, url, length, lengthTicks, rangeEl, loading, error, result, copied, preview, displayUrl, qr, ratio, normalize, setLength, onRangeInput, onRangeCommit, onSliderPointerDown, onSliderPointerMove, onSliderPointerUp, updateUrl, submit, paste, onPaste, copyResult };
    },
    template: `
      <div class="ambient ambient-a" aria-hidden="true"></div><div class="ambient ambient-b" aria-hidden="true"></div>
      <div class="e-field" aria-hidden="true"><span>eeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeee</span></div>
      <main class="wrap">
        <header class="topline"><a class="brand" href="/" aria-label="EEEE home"><i></i><span>EEEE</span></a><div class="header-right"><span class="status-dot"><b></b> {{ copy.online }}</span><div class="lang-switch" :aria-label="copy.language"><a :class="{active:lang==='en'}" :href="langUrl('en')">EN</a><a :class="{active:lang==='zh'}" :href="langUrl('zh')">中文</a></div></div></header>
        <template v-if="isLost"><section class="hero lost-hero"><div class="eyebrow"><span>404</span> / {{ copy.lostTitle }}</div><h1><span class="ghost-e">e</span>404</h1><p>{{ copy.lostText }}</p></section><section class="lost-card card"><div class="lost-orbit"><span>e</span><span>e</span><span>e</span></div><div><div class="card-kicker">{{ copy.lostSignal }}</div><p class="muted">{{ copy.lostHint }}</p><a class="btn" href="/"><span>↗</span> {{ copy.backHome }}</a></div></section></template>
        <template v-else><section class="hero home-hero"><div class="eyebrow"><span class="pulse"></span> {{ copy.eyebrow }}</div><h1>{{ copy.heroTitle }}<br><em>{{ copy.heroAccent }}</em></h1><p>{{ copy.tagline }}</p></section><section class="card generator-card"><div class="card-head"><div><div class="card-kicker">01 / {{ copy.originalUrl }}</div><h2>{{ copy.inputTitle }}</h2></div><span class="shortcut">⌘ ↵</span></div><form @submit.prevent="submit"><div class="url-field" :class="{'has-value':url,'is-valid':/^https?:\\/\\/[^\\s]+$/i.test(normalize(url))}"><span class="field-icon">↗</span><input v-model="url" @input="updateUrl" @paste="onPaste" @keydown.meta.enter="submit" @keydown.ctrl.enter="submit" type="text" placeholder="example.com/your-destination" autocomplete="off" spellcheck="false"><button class="paste-btn" type="button" @click="paste">⌘V</button></div><div class="field-note"><span>{{ url ? copy.validUrl : 'https:// will be added automatically' }}</span><span>{{ url.length }}</span></div><div class="length-head"><div><div class="card-kicker">02 / {{ copy.length }}</div><h2>{{ copy.lengthTitle }}</h2></div><strong id="len-label"><span id="len-value">100</span> <small>e</small></strong></div><div class="len-wrap" :style="{'--range-progress':ratio}"><div class="len-slider" @pointerdown.prevent="onSliderPointerDown" @pointermove.prevent="onSliderPointerMove" @pointerup="onSliderPointerUp" @pointercancel="onSliderPointerUp"><input id="length-range" ref="rangeEl" type="range" :min="min" :max="max" step="any" value="100" tabindex="0" @input="onRangeInput" @change="onRangeCommit" :aria-valuemin="min" :aria-valuemax="max" :aria-valuenow="length" :aria-valuetext="length + ' e'"></div><div class="len-ticks"><button v-for="n in lengthTicks" :key="n" type="button" class="len-tick" :class="{active:n===length}" @click="setLength(n)">{{ n }}</button></div><div class="number-field"><input type="number" :min="min" :max="max" :value="length" @input="setLength($event.target.value)"><span>e</span></div></div><div class="preview-line"><span class="preview-mark">◎</span><span>{{ preview }}</span></div><button class="btn generate-btn" :class="{'is-loading':loading}" :disabled="loading"><span class="btn-icon">{{ loading ? '◌' : '↗' }}</span><span class="btn-label">{{ loading ? copy.generating : copy.generate }}</span><kbd>⌘↵</kbd></button><div class="err" role="alert">{{ error }}</div></form><div v-if="result" class="result show"><div class="result-head"><div class="card-kicker">{{ copy.longUrl }}</div><span class="success-chip">✓ ready</span></div><div class="longurl">{{ displayUrl }}</div><div class="meta"><span>{{ result.length }} e</span><span class="meta-sep">•</span><span>{{ result.target }}</span></div><div class="e-qr-wrap"><div class="card-kicker">{{ copy.qrTitle }}</div><div v-if="qr.ok" class="e-qr" :class="{huge: qr.size > 90}" :style="{'--qr-size': qr.gridSize}" role="img" :aria-label="copy.qrTitle"><template v-for="(row, y) in qr.modules" :key="y"><span v-for="(dark, x) in row" :key="x" class="e-qr-cell" :class="{dark}"><i v-if="dark">e</i></span></template></div><p class="e-qr-note">{{ qr.ok ? copy.qrHint : copy.qrTooLong }}</p></div><div class="actions"><button class="btn" :class="{copied}" type="button" @click="copyResult"><span>{{ copied ? '✓' : '⬠' }}</span> {{ copied ? copy.copied : copy.copy }}</button><a class="btn ghost" :href="result.url" target="_blank" rel="noopener"><span>↗</span> {{ copy.open }}</a></div></div></section><div class="footer"><span>EEEE</span> · {{ copy.description }}<span class="footer-e">e e e</span></div></template>
      </main>`
  }).mount(root);
})();
