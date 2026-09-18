<?php
declare(strict_types=1);
/** @var array $config */
/** @var string $page */

$base = Helpers::baseUrl($config ?? ['app' => ['base_url' => '']]);
$csrf = Helpers::csrfToken();
$title = $title ?? 'EEEE — Long URL Generator';
$desc = I18n::t('description');
$minLen = (int) ($config['app']['min_length'] ?? 8);
$maxLen = (int) ($config['app']['max_length'] ?? 5000);
?><!doctype html>
<html lang="<?= Helpers::h(I18n::locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::h($title) ?></title>
  <meta name="description" content="<?= Helpers::h($desc) ?>">
  <meta property="og:title" content="EEEE — Long URL Generator">
  <meta property="og:description" content="<?= Helpers::h($desc) ?>">
  <meta property="og:type" content="website">
  <link rel="stylesheet" href="/assets/app.css">
  <link rel="stylesheet" href="/assets/slider.css">
</head>
<body
  data-base="<?= Helpers::h($base) ?>"
  data-generate="<?= Helpers::h(I18n::t('generate')) ?>"
  data-generating="<?= Helpers::h(I18n::t('generating')) ?>"
  data-copy="<?= Helpers::h(I18n::t('copy')) ?>"
  data-copied="<?= Helpers::h(I18n::t('copied')) ?>"
  data-valid-url="<?= Helpers::h(I18n::t('valid_url')) ?>"
  data-paste="<?= Helpers::h(I18n::t('paste')) ?>"
>
  <div class="ambient ambient-a" aria-hidden="true"></div>
  <div class="ambient ambient-b" aria-hidden="true"></div>
  <div class="e-field" aria-hidden="true"><span>eeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeeeeeeeeeee</span><span>eeeeeeeeeeeeeeee</span></div>
  <main class="wrap">
    <header class="topline">
      <a class="brand" href="/" aria-label="EEEE home"><i></i><span>EEEE</span></a>
      <div class="header-right">
        <span class="status-dot"><b></b> <?= Helpers::h(I18n::t('online')) ?></span>
        <div class="lang-switch" aria-label="<?= Helpers::h(I18n::t('language')) ?>">
          <a class="<?= I18n::locale() === 'en' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('en')) ?>">EN</a>
          <a class="<?= I18n::locale() === 'zh' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('zh')) ?>">中文</a>
        </div>
      </div>
    </header>

    <?php if (($page ?? '') === 'lost'): ?>
      <section class="hero lost-hero">
        <div class="eyebrow"><span>404</span> / <?= Helpers::h(I18n::t('lost_title')) ?></div>
        <h1><span class="ghost-e">e</span>404</h1>
        <p><?= Helpers::h(I18n::t('lost_text')) ?></p>
      </section>
      <section class="lost-card card">
        <div class="lost-orbit" aria-hidden="true"><span>e</span><span>e</span><span>e</span></div>
        <div>
          <div class="card-kicker"><?= Helpers::h(I18n::t('lost_signal')) ?></div>
          <p class="muted"><?= Helpers::h(I18n::t('lost_hint')) ?></p>
          <a class="btn" href="/"><span>↗</span> <?= Helpers::h(I18n::t('back_home')) ?></a>
        </div>
      </section>
    <?php else: ?>
      <section class="hero home-hero">
        <div class="eyebrow"><span class="pulse"></span> <?= Helpers::h(I18n::t('eyebrow')) ?></div>
        <h1><?= Helpers::h(I18n::t('hero_title')) ?><br><em><?= Helpers::h(I18n::t('hero_accent')) ?></em></h1>
        <p><?= Helpers::h(I18n::t('tagline')) ?></p>
      </section>

      <section class="card generator-card">
        <div class="card-head">
          <div><div class="card-kicker">01 / <?= Helpers::h(I18n::t('original_url')) ?></div><h2><?= Helpers::h(I18n::t('input_title')) ?></h2></div>
          <span class="shortcut">⌘ ↵</span>
        </div>
        <form id="gen-form" novalidate>
          <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
          <div class="url-field">
            <span class="field-icon">↗</span>
            <input id="url" name="url" type="text" placeholder="example.com/your-destination" required autocomplete="off" spellcheck="false">
            <button class="paste-btn" type="button" id="paste" aria-label="<?= Helpers::h(I18n::t('paste')) ?>">⌘V</button>
          </div>
          <div class="field-note"><span id="url-state">https:// will be added automatically</span><span id="url-count">0</span></div>

          <div class="length-head">
            <div><div class="card-kicker">02 / <?= Helpers::h(I18n::t('length')) ?></div><h2><?= Helpers::h(I18n::t('length_title')) ?></h2></div>
            <strong id="len-label">100 <small>e</small></strong>
          </div>
          <div class="len-wrap">
            <input id="length-range" type="range" min="<?= $minLen ?>" max="<?= $maxLen ?>" step="1" value="100" aria-label="<?= Helpers::h(I18n::t('length')) ?>">
            <div class="len-ticks">
              <?php foreach ([50,100,200,500,1000,2000] as $n): ?>
                <?php if ($n >= $minLen && $n <= $maxLen): ?><button type="button" class="len-tick<?= $n === 100 ? ' active' : '' ?>" data-len="<?= $n ?>"><?= $n ?></button><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <div class="number-field"><input id="length" name="length" type="number" min="<?= $minLen ?>" max="<?= $maxLen ?>" value="100" aria-label="<?= Helpers::h(I18n::t('length')) ?>"><span>e</span></div>
          </div>
          <div class="preview-line"><span class="preview-mark">◎</span><span id="preview"></span></div>
          <button class="btn generate-btn" id="submit" type="submit"><span class="btn-icon">↗</span><span class="btn-label"><?= Helpers::h(I18n::t('generate')) ?></span><kbd>⌘↵</kbd></button>
          <div class="err" id="err" role="alert"></div>
        </form>

        <div class="result" id="result" aria-live="polite">
          <div class="result-head"><div class="card-kicker"><?= Helpers::h(I18n::t('long_url')) ?></div><span class="success-chip">✓ ready</span></div>
          <div class="longurl" id="out-url"></div>
          <div class="meta"><span id="out-len"></span><span class="meta-sep">•</span><span id="out-target"></span></div>
          <div class="actions"><button class="btn" type="button" id="copy"><span>⧉</span><span class="btn-label"><?= Helpers::h(I18n::t('copy')) ?></span></button><a class="btn ghost" id="open" target="_blank" rel="noopener"><span>↗</span> <?= Helpers::h(I18n::t('open')) ?></a></div>
        </div>
      </section>
      <div class="footer"><span>EEEE</span> · <?= Helpers::h(I18n::t('description')) ?><span class="footer-e">e e e</span></div>
    <?php endif; ?>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
