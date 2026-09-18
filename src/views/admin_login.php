<?php
declare(strict_types=1);
$csrf = Helpers::csrfToken();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EEEE Admin</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="wrap" style="max-width:420px">
    <div class="brand">EEEE</div>
    <div class="hero"><h1>Admin</h1><p>Sign in to the long URL console.</p></div>
    <div class="card">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
        <label>Password</label>
        <input type="password" name="password" required>
        <div class="actions"><button class="btn" type="submit">Sign in</button></div>
        <?php if (!empty($error)): ?><div class="err"><?= Helpers::h($error) ?></div><?php endif; ?>
      </form>
    </div>
  </div>
</body>
</html>
