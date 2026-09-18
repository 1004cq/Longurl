<?php
declare(strict_types=1);
/** @var string $view */
/** @var array $stats */
/** @var array $recentLinks */
/** @var array $recentClicks */
/** @var array $list */
/** @var string $q */
/** @var array $config */
$csrf = Helpers::csrfToken();
$nav = [
    'dashboard' => 'Dashboard',
    'links' => 'Links',
    'analytics' => 'Analytics',
    'api' => 'API',
    'settings' => 'Settings',
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EEEE Admin</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="layout">
  <aside class="side">
    <div class="brand" style="margin:0 12px 18px">EEEE</div>
    <?php foreach ($nav as $k => $label): ?>
      <a class="<?= $view === $k ? 'active' : '' ?>" href="/admin/?view=<?= Helpers::h($k) ?>"><?= Helpers::h($label) ?></a>
    <?php endforeach; ?>
    <a href="/admin/?view=logout">Logout</a>
  </aside>
  <main class="main">
    <?php if ($view === 'dashboard'): ?>
      <div class="stats">
        <div class="card stat"><span class="muted">Links</span><b><?= (int) $stats['links'] ?></b></div>
        <div class="card stat"><span class="muted">Clicks</span><b><?= (int) $stats['clicks'] ?></b></div>
        <div class="card stat"><span class="muted">Today</span><b><?= (int) $stats['today'] ?></b></div>
      </div>
      <div class="card">
        <h3>Recent links</h3>
        <table class="table">
          <tr><th>e</th><th>Target</th><th>Clicks</th><th>Created</th></tr>
          <?php foreach ($recentLinks as $row): ?>
            <tr>
              <td><?= (int) $row['e_length'] ?></td>
              <td class="break"><?= Helpers::h($row['target_url']) ?></td>
              <td><?= (int) $row['clicks'] ?></td>
              <td><?= Helpers::h($row['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="card" style="margin-top:14px">
        <h3>Recent visits</h3>
        <table class="table">
          <tr><th>Time</th><th>e</th><th>IP</th><th>UA</th></tr>
          <?php foreach ($recentClicks as $row): ?>
            <tr>
              <td><?= Helpers::h($row['clicked_at']) ?></td>
              <td><?= (int) $row['e_length'] ?></td>
              <td><?= Helpers::h($row['ip']) ?></td>
              <td class="break"><?= Helpers::h($row['user_agent']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php elseif ($view === 'links'): ?>
      <form class="search" method="get">
        <input type="hidden" name="view" value="links">
        <input type="text" name="q" value="<?= Helpers::h($q) ?>" placeholder="Search URL or length">
        <button class="btn" type="submit">Search</button>
      </form>
      <div class="card">
        <table class="table">
          <tr><th>ID</th><th>e length</th><th>Target</th><th>Clicks</th><th>Enabled</th><th>Created</th><th></th></tr>
          <?php foreach ($list['rows'] as $row): ?>
            <tr>
              <td><?= (int) $row['id'] ?></td>
              <td><?= (int) $row['e_length'] ?></td>
              <td class="break"><?= Helpers::h($row['target_url']) ?></td>
              <td><?= (int) $row['clicks'] ?></td>
              <td><?= ((int)$row['enabled']) ? 'yes' : 'no' ?></td>
              <td><?= Helpers::h($row['created_at']) ?></td>
              <td>
                <form method="post" style="display:flex;gap:6px">
                  <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <?php if ((int)$row['enabled']): ?>
                    <button class="btn ghost" name="act" value="disable">Disable</button>
                  <?php else: ?>
                    <button class="btn ghost" name="act" value="enable">Enable</button>
                  <?php endif; ?>
                  <button class="btn danger" name="act" value="delete" onclick="return confirm('Delete?')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <p class="muted">Total <?= (int) $list['total'] ?></p>
      </div>
    <?php elseif ($view === 'analytics'): ?>
      <div class="card">
        <table class="table">
          <tr><th>Time</th><th>Link</th><th>IP</th><th>Referer</th><th>URI</th><th>UA</th></tr>
          <?php foreach ($recentClicks as $row): ?>
            <tr>
              <td><?= Helpers::h($row['clicked_at']) ?></td>
              <td><?= (int) $row['e_length'] ?> e</td>
              <td><?= Helpers::h($row['ip']) ?></td>
              <td class="break"><?= Helpers::h($row['referer']) ?></td>
              <td class="break"><?= Helpers::h($row['request_uri']) ?></td>
              <td class="break"><?= Helpers::h($row['user_agent']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php elseif ($view === 'api'): ?>
      <div class="card">
        <p>POST <code>/api/create</code></p>
        <p class="muted">Header: <code>Authorization: Bearer YOUR_TOKEN</code> or field <code>token</code></p>
        <p class="muted">Fields: <code>url</code>, <code>length</code></p>
        <p class="muted">Token is stored in config/local.php and generated during install. It is not shown here.</p>
      </div>
    <?php else: ?>
      <div class="card">
        <p>Base URL is configured at install time in <code>config/local.php</code> (outside the web root).</p>
        <p class="muted">To rotate the API token or admin password, edit that file on the server. Do not commit it.</p>
      </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
