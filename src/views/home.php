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
$copy = [
    'language' => I18n::t('language'), 'online' => I18n::t('online'), 'eyebrow' => I18n::t('eyebrow'),
    'tagline' => I18n::t('tagline'), 'heroTitle' => I18n::t('hero_title'), 'heroAccent' => I18n::t('hero_accent'),
    'inputTitle' => I18n::t('input_title'), 'originalUrl' => I18n::t('original_url'), 'paste' => I18n::t('paste'),
    'validUrl' => I18n::t('valid_url'), 'lengthTitle' => I18n::t('length_title'), 'length' => I18n::t('length'),
    'generate' => I18n::t('generate'), 'generating' => I18n::t('generating'), 'longUrl' => I18n::t('long_url'),
    'copy' => I18n::t('copy'), 'copied' => I18n::t('copied'), 'open' => I18n::t('open'),
    'lostTitle' => I18n::t('lost_title'), 'lostText' => I18n::t('lost_text'), 'lostSignal' => I18n::t('lost_signal'),
    'lostHint' => I18n::t('lost_hint'), 'backHome' => I18n::t('back_home'), 'description' => $desc,
];
?><!doctype html>
<html lang="<?= Helpers::h(I18n::locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= Helpers::h($title) ?></title>
  <meta name="description" content="<?= Helpers::h($desc) ?>">
  <meta property="og:title" content="EEEE — Long URL Generator">
  <meta property="og:description" content="<?= Helpers::h($desc) ?>">
  <meta property="og:type" content="website">
  <link rel="stylesheet" href="<?= Helpers::h(Helpers::assetUrl('app.css')) ?>">
  <link rel="stylesheet" href="<?= Helpers::h(Helpers::assetUrl('slider.css')) ?>">
</head>
<body class="eeee-app" data-base="<?= Helpers::h($base) ?>" data-page="<?= Helpers::h($page ?? 'home') ?>" data-min-length="<?= $minLen ?>" data-max-length="<?= $maxLen ?>" data-csrf="<?= Helpers::h($csrf) ?>">
  <canvas id="eeee-scene" aria-hidden="true"></canvas>
  <div id="app"></div>
  <script>window.EEEE_COPY = <?= json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="<?= Helpers::h(Helpers::assetUrl('scene.js')) ?>"></script>
  <script src="<?= Helpers::h(Helpers::assetUrl('app.js')) ?>"></script>
</body>
</html>
