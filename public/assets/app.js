(() => {
  const { createApp, ref, computed, onMounted, nextTick } = window.Vue;
  const root = document.getElementById('app');
  const body = document.body;
  if (!root || !window.Vue) return;
  const copy = window.EEEE_COPY || {};
  const isLost = body.dataset.page === 'lost';
  const min = Number(body.dataset.minLength || 8), max = Number(body.dataset.maxLength || 5000);
  const base = body.dataset.base || window.location.origin;
  const lang = document.documentElement.lang || 'en';
  const clamp = (n) => Math.min(max, Math.max(min, Number(n) || min));
  const normalize = (value) => { const v = String(value || '').trim(); return v && /^[a-z][a-z0-9+.-]*:\/\//i.test(v) ? v : (v ? `https://${v}` : ''); };
  const langUrl = (next) => `${window.location.pathname}?lang=${next}`;

  // iOS/Safari can still rubber-band even with CSS overscroll-behavior.
  // Keep normal page scrolling, but block dragging beyond the real document edges.
  let touchStartY = 0;
  document.addEventListener('touchstart', (event) => {
    if (event.touches.length === 1) touchStartY = event.touches[0].clientY;
  }, { passive: true });
  document.addEventListener('touchmove', (event) => {
    if (event.touches.length !== 1) return;
    const y = event.touches[0].clientY;
    const delta = y - touchStartY;
    const root = document.scrollingElement || document.documentElement;
    const maxScroll = Math.max(0, root.scrollHeight - root.clientHeight);
    const atTop = root.scrollTop <= 0;
    const atBottom = root.scrollTop >= maxScroll - 1;
    if ((atTop && delta > 0) || (atBottom && delta < 0)) event.preventDefault();
  }, { passive: false });

  createApp({
    setup() {
      const url = ref(''), length = ref(100), loading = ref(false), error = ref(''), result = ref(null), copied = ref(false);
      const preview = computed(() => `${base}/` + 'e'.repeat(Math.min(length.value, 42)) + (length.value > 42 ? '…' : '') + `  ·  ${length.value} e`);
      const ratio = computed(() => `${((length.value - min) / Math.max(1, max - min)) * 100}%`);
      const setLength = (value) => { length.value = clamp(value); window.EEEEScene?.setLength(length.value); };
      const updateUrl = () => { if (url.value) error.value = ''; };
      const submit = async () => {
        error.value = ''; copied.value = false;
        if (!url.value.trim()) { error.value = 'Please enter a URL.'; return; }
        loading.value = true;
        try {
          const fd = new FormData(); fd.set('url', normalize(url.value)); fd.set('length', String(length.value));
          const response = await fetch('/api/create', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
          const data = await response.json(); if (!data.success) throw new Error(data.error || 'Failed');
          result.value = data; window.EEEEScene?.success(data.length); await nextTick(); document.querySelector('.result')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) { error.value = e instanceof Error ? e.message : 'Failed'; }
        finally { loading.value = false; }
      };
      const paste = async () => { try { url.value = await navigator.clipboard.readText(); updateUrl(); } catch {} };
      const copyResult = async () => { if (!result.value) return; try { await navigator.clipboard.writeText(result.value.url); copied.value = true; setTimeout(() => copied.value = false, 1600); } catch {} };
      onMounted(() => { setLength(100); if (isLost) window.EEEEScene?.lost(); document.documentElement.classList.add('js-ready'); requestAnimationFrame(() => body.classList.add('is-ready')); });
      return { copy, lang, langUrl, isLost, min, max, url, length, loading, error, result, copied, preview, ratio, normalize, setLength, updateUrl, submit, paste, copyResult };
    },
    template: `
      <div class="ambient ambient-a" aria-hidden="true"></div><div class="ambient ambient-b" aria-hidden="true"></div>
      <div class="e-field" aria-hidden="true"><span>eeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeee</span></div>
      <main class="wrap">
        <header class="topline"><a class="brand" href="/" aria-label="EEEE home"><i></i><span>EEEE</span></a><div class="header-right"><span class="status-dot"><b></b> {{ copy.online }}</span><div class="lang-switch" :aria-label="copy.language"><a :class="{active:lang==='en'}" :href="langUrl('en')">EN</a><a :class="{active:lang==='zh'}" :href="langUrl('zh')">中文</a></div></div></header>
        <template v-if="isLost"><section class="hero lost-hero"><div class="eyebrow"><span>404</span> / {{ copy.lostTitle }}</div><h1><span class="ghost-e">e</span>404</h1><p>{{ copy.lostText }}</p></section><section class="lost-card card"><div class="lost-orbit"><span>e</span><span>e</span><span>e</span></div><div><div class="card-kicker">{{ copy.lostSignal }}</div><p class="muted">{{ copy.lostHint }}</p><a class="btn" href="/"><span>↗</span> {{ copy.backHome }}</a></div></section></template>
        <template v-else><section class="hero home-hero"><div class="eyebrow"><span class="pulse"></span> {{ copy.eyebrow }}</div><h1>{{ copy.heroTitle }}<br><em>{{ copy.heroAccent }}</em></h1><p>{{ copy.tagline }}</p></section><section class="card generator-card"><div class="card-head"><div><div class="card-kicker">01 / {{ copy.originalUrl }}</div><h2>{{ copy.inputTitle }}</h2></div><span class="shortcut">⌘ ↵</span></div><form @submit.prevent="submit"><div class="url-field" :class="{'has-value':url,'is-valid':/^https?:\\/\\/[^\\s]+$/i.test(normalize(url))}"><span class="field-icon">↗</span><input v-model="url" @input="updateUrl" @keydown.meta.enter="submit" @keydown.ctrl.enter="submit" type="text" placeholder="example.com/your-destination" autocomplete="off" spellcheck="false"><button class="paste-btn" type="button" @click="paste">⌘V</button></div><div class="field-note"><span>{{ url ? copy.validUrl : 'https:// will be added automatically' }}</span><span>{{ url.length }}</span></div><div class="length-head"><div><div class="card-kicker">02 / {{ copy.length }}</div><h2>{{ copy.lengthTitle }}</h2></div><strong id="len-label">{{ length }} <small>e</small></strong></div><div class="len-wrap"><input id="length-range" type="range" :min="min" :max="max" v-model.number="length" @input="setLength(length)" :style="{'--range-progress':ratio}"><div class="len-ticks"><button v-for="n in [50,100,200,500,1000,2000]" v-if="n>=min&&n<=max" type="button" class="len-tick" :class="{active:n===length}" @click="setLength(n)">{{ n }}</button></div><div class="number-field"><input type="number" :min="min" :max="max" v-model.number="length" @input="setLength(length)"><span>e</span></div></div><div class="preview-line"><span class="preview-mark">◎</span><span>{{ preview }}</span></div><button class="btn generate-btn" :class="{'is-loading':loading}" :disabled="loading"><span class="btn-icon">{{ loading ? '◌' : '↗' }}</span><span class="btn-label">{{ loading ? copy.generating : copy.generate }}</span><kbd>⌘↵</kbd></button><div class="err" role="alert">{{ error }}</div></form><div v-if="result" class="result show"><div class="result-head"><div class="card-kicker">{{ copy.longUrl }}</div><span class="success-chip">✓ ready</span></div><div class="longurl">{{ result.url }}</div><div class="meta"><span>{{ result.length }} e</span><span class="meta-sep">•</span><span>{{ result.target }}</span></div><div class="actions"><button class="btn" :class="{copied}" type="button" @click="copyResult"><span>{{ copied ? '✓' : '⧉' }}</span> {{ copied ? copy.copied : copy.copy }}</button><a class="btn ghost" :href="result.url" target="_blank" rel="noopener"><span>↗</span> {{ copy.open }}</a></div></div></section><div class="footer"><span>EEEE</span> · {{ copy.description }}<span class="footer-e">e e e</span></div></template>
      </main>`
  }).mount(root);
})();
