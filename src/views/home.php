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
>
  <div class="wrap">
    <div class="topline">
      <div class="brand">EEEE</div>
      <div class="lang-switch">
        <a class="<?= I18n::locale() === 'en' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('en')) ?>">EN</a>
        <a class="<?= I18n::locale() === 'zh' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('zh')) ?>">中文</a>
      </div>
    </div>

    <?php if (($page ?? '') === 'lost'): ?>
      <div class="hero">
        <h1>404</h1>
        <p><?= Helpers::h(I18n::t('lost_title')) ?></p>
      </div>
      <div class="card">
        <p class="muted"><?= Helpers::h(I18n::t('lost_text')) ?></p>
        <div class="actions">
          <a class="btn" href="/"><?= Helpers::h(I18n::t('back_home')) ?></a>
        </div>
      </div>
    <?php else: ?>
      <div class="hero">
        <h1>EEEE</h1>
        <p><?= Helpers::h(I18n::t('tagline')) ?></p>
      </div>
      <div class="card">
        <form id="gen-form">
          <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
          <label for="url"><?= Helpers::h(I18n::t('original_url')) ?></label>
          <input id="url" name="url" type="text" placeholder="example.com/test" required autocomplete="off">

          <label style="margin-top:16px" for="length-range"><?= Helpers::h(I18n::t('length')) ?></label>
          <div class="len-wrap">
            <div class="len-top">
              <strong id="len-label">100 e</strong>
            </div>
            <input
              id="length-range"
              type="range"
              min="<?= $minLen ?>"
              max="<?= $maxLen ?>"
              step="1"
              value="100"
              aria-label="<?= Helpers::h(I18n::t('length')) ?>"
            >
            <div class="len-ticks">
              <?php foreach ([50,100,200,500,1000,2000] as $n): ?>
                <?php if ($n >= $minLen && $n <= $maxLen): ?>
                  <button type="button" class="len-tick<?= $n === 100 ? ' active' : '' ?>" data-len="<?= $n ?>"><?= $n ?></button>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <input
              id="length"
              name="length"
              type="number"
              min="<?= $minLen ?>"
              max="<?= $maxLen ?>"
              value="100"
            >
          </div>

          <div class="meta" id="preview"></div>
          <button class="btn" id="submit" type="submit"><?= Helpers::h(I18n::t('generate')) ?></button>
          <div class="err" id="err"></div>
        </form>

        <div class="result card" id="result" style="margin-top:16px;padding:16px">
          <div class="muted"><?= Helpers::h(I18n::t('long_url')) ?></div>
          <div class="longurl" id="out-url"></div>
          <div class="meta"><span id="out-len"></span> · <span id="out-target"></span></div>
          <div class="actions">
            <button class="btn" type="button" id="copy"><?= Helpers::h(I18n::t('copy')) ?></button>
            <a class="btn ghost" id="open" target="_blank" rel="noopener"><?= Helpers::h(I18n::t('open')) ?></a>
          </div>
        </div>
      </div>

      <div class="footer"><?= Helpers::h(I18n::t('description')) ?></div>
    <?php endif; ?>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
