<?php
declare(strict_types=1);
$csrf = Helpers::csrfToken();
?><!doctype html>
<html lang="<?= Helpers::h(I18n::locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EEEE Admin</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="wrap" style="max-width:420px">
    <div class="topline">
      <div class="brand">EEEE</div>
      <div class="lang-switch">
        <a class="<?= I18n::locale() === 'en' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('en')) ?>">EN</a>
        <a class="<?= I18n::locale() === 'zh' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('zh')) ?>">中文</a>
      </div>
    </div>
    <div class="hero"><h1>Admin</h1><p><?= Helpers::h(I18n::t('sign_in_text')) ?></p></div>
    <div class="card">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
        <label><?= Helpers::h(I18n::t('password')) ?></label>
        <input type="password" name="password" required autocomplete="current-password">
        <div class="actions"><button class="btn" type="submit"><?= Helpers::h(I18n::t('sign_in')) ?></button></div>
        <?php if (!empty($error)): ?><div class="err"><?= Helpers::h($error) ?></div><?php endif; ?>
      </form>
    </div>
  </div>
</body>
</html>
