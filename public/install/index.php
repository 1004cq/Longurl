<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
}

$locked = app_installed();
$error = '';
$ok = false;

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $port = trim((string) ($_POST['db_port'] ?? '3306'));
    $name = trim((string) ($_POST['db_name'] ?? ''));
    $user = trim((string) ($_POST['db_user'] ?? ''));
    $pass = (string) ($_POST['db_pass'] ?? '');
    $base = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    if ($name === '' || $user === '' || $base === '' || $adminPass === '') {
        $error = 'Please fill all required fields.';
    } elseif (!preg_match('#^https?://#i', $base)) {
        $error = 'Website URL must start with http:// or https://';
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $sql = file_get_contents(BASE_PATH . '/sql/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('Missing schema.sql');
            }
            $pdo->exec($sql);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $token = Helpers::randomToken(32);
            $config = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'db' => [\n        'dsn' => " . var_export($dsn, true) . ",\n        'user' => " . var_export($user, true) . ",\n        'pass' => " . var_export($pass, true) . ",\n    ],\n    'app' => [\n        'base_url' => " . var_export($base, true) . ",\n        'min_length' => 8,\n        'max_length' => 5000,\n        'name' => 'EEEE',\n    ],\n    'admin' => [\n        'password_hash' => " . var_export($hash, true) . ",\n        'api_token' => " . var_export($token, true) . ",\n    ],\n    'rate' => [\n        'create_per_minute' => 20,\n        'api_per_minute' => 60,\n        'login_per_minute' => 8,\n    ],\n];\n";
            if (!is_dir(BASE_PATH . '/config')) {
                mkdir(BASE_PATH . '/config', 0750, true);
            }
            if (!is_dir(BASE_PATH . '/storage')) {
                mkdir(BASE_PATH . '/storage', 0750, true);
            }
            if (file_put_contents(CONFIG_FILE, $config) === false) {
                throw new RuntimeException('Cannot write config file.');
            }
            file_put_contents(LOCK_FILE, date('c'));
            $ok = true;
        } catch (Throwable $e) {
            $error = 'Install failed. Check database credentials and directory permissions.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install EEEE</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="wrap" style="max-width:560px">
    <div class="brand">EEEE</div>
    <div class="hero"><h1>Install</h1><p>Long URL generator setup.</p></div>
    <div class="card">
      <?php if ($locked): ?>
        <p>Installer is locked.</p>
      <?php elseif ($ok): ?>
        <p class="ok">Installed. Save your API token from config/local.php on the server.</p>
        <div class="actions"><a class="btn" href="/admin/">Open admin</a></div>
      <?php else: ?>
        <form method="post">
          <label>DB host</label>
          <input name="db_host" value="127.0.0.1" required>
          <label style="margin-top:12px">DB port</label>
          <input name="db_port" value="3306" required>
          <label style="margin-top:12px">DB name</label>
          <input name="db_name" required>
          <label style="margin-top:12px">DB user</label>
          <input name="db_user" required>
          <label style="margin-top:12px">DB password</label>
          <input name="db_pass" type="password">
          <label style="margin-top:12px">Website URL</label>
          <input name="base_url" placeholder="https://your-domain.example" required>
          <label style="margin-top:12px">Admin password</label>
          <input name="admin_pass" type="password" required>
          <div class="actions"><button class="btn" type="submit">Install</button></div>
          <?php if ($error): ?><div class="err"><?= Helpers::h($error) ?></div><?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
