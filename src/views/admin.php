<?php
declare(strict_types=1);

/** @var string $view */
/** @var array $stats */
/** @var array $dailyClicks */
/** @var array $recentLinks */
/** @var array $recentClicks */
/** @var array $list */
/** @var string $q */
/** @var array $config */
/** @var ?array $editLink */
/** @var string $notice */
/** @var string $error */

$csrf = Helpers::csrfToken();
$nav = [
    'dashboard' => 'Dashboard',
    'links' => 'Links',
    'analytics' => 'Analytics',
    'api' => 'API',
    'settings' => 'Settings',
];
$maxDaily = 1;
foreach ($dailyClicks as $point) {
    $maxDaily = max($maxDaily, (int) $point['clicks']);
}
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
    <div class="side-head">
      <div class="brand">EEEE</div>
      <span class="badge">ADMIN</span>
    </div>
    <nav>
      <?php foreach ($nav as $k => $label): ?>
        <a class="<?= $view === $k ? 'active' : '' ?>" href="/admin/?view=<?= Helpers::h($k) ?>"><?= Helpers::h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <a class="logout" href="/admin/?view=logout">Logout</a>
  </aside>

  <main class="main">
    <div class="admin-top">
      <div>
        <p class="eyebrow">EEEE Console</p>
        <h1><?= Helpers::h($nav[$view] ?? 'Dashboard') ?></h1>
      </div>
      <a class="btn ghost" href="/" target="_blank" rel="noopener">Open site</a>
    </div>

    <?php if ($notice !== ''): ?><div class="notice"><?= Helpers::h($notice) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert"><?= Helpers::h($error) ?></div><?php endif; ?>

    <?php if ($view === 'dashboard'): ?>
      <div class="stats stats-4">
        <div class="card stat"><span class="muted">Links</span><b><?= (int) $stats['links'] ?></b></div>
        <div class="card stat"><span class="muted">Active</span><b><?= (int) $stats['active'] ?></b></div>
        <div class="card stat"><span class="muted">Clicks</span><b><?= (int) $stats['clicks'] ?></b></div>
        <div class="card stat"><span class="muted">Today</span><b><?= (int) $stats['today'] ?></b></div>
      </div>

      <div class="card">
        <div class="section-head">
          <div><p class="eyebrow">Traffic</p><h3>Last 7 days</h3></div>
        </div>
        <div class="bars" aria-label="Clicks during the last 7 days">
          <?php foreach ($dailyClicks as $point): ?>
            <?php $height = max(4, (int) round(((int) $point['clicks'] / $maxDaily) * 100)); ?>
            <div class="bar-col">
              <div class="bar-value"><?= (int) $point['clicks'] ?></div>
              <div class="bar-track"><div class="bar-fill" style="height:<?= $height ?>%"></div></div>
              <div class="bar-label"><?= Helpers::h(substr((string) $point['day'], 5)) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="card">
          <div class="section-head"><h3>Recent links</h3><a class="text-link" href="/admin/?view=links">View all</a></div>
          <div class="table-wrap">
            <table class="table">
              <tr><th>e</th><th>Target</th><th>Clicks</th><th>Created</th></tr>
              <?php foreach (array_slice($recentLinks, 0, 8) as $row): ?>
                <tr>
                  <td><span class="badge"><?= (int) $row['e_length'] ?></span></td>
                  <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                  <td><?= (int) $row['clicks'] ?></td>
                  <td><?= Helpers::h($row['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="section-head"><h3>Recent visits</h3><a class="text-link" href="/admin/?view=analytics">View all</a></div>
          <div class="table-wrap">
            <table class="table">
              <tr><th>Time</th><th>e</th><th>IP</th></tr>
              <?php foreach (array_slice($recentClicks, 0, 8) as $row): ?>
                <tr>
                  <td><?= Helpers::h($row['clicked_at']) ?></td>
                  <td><?= (int) $row['e_length'] ?></td>
                  <td><?= Helpers::h($row['ip']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($view === 'links'): ?>
      <?php if ($editLink): ?>
        <div class="card edit-card">
          <div class="section-head">
            <div>
              <p class="eyebrow">Edit link</p>
              <h3><?= (int) $editLink['e_length'] ?> e's</h3>
            </div>
            <a class="text-link" href="/admin/?view=links">Close</a>
          </div>
          <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $editLink['id'] ?>">
            <input type="hidden" name="act" value="update_link">
            <div class="full">
              <label>Target URL</label>
              <input type="url" name="target_url" value="<?= Helpers::h($editLink['target_url']) ?>" required>
            </div>
            <div>
              <label>Expires at</label>
              <?php
                $expiresValue = '';
                if (!empty($editLink['expires_at'])) {
                    $expiresValue = date('Y-m-d\TH:i', strtotime((string) $editLink['expires_at']));
                }
              ?>
              <input type="datetime-local" name="expires_at" value="<?= Helpers::h($expiresValue) ?>">
            </div>
            <div>
              <label>Long URL</label>
              <input type="text" readonly value="<?= Helpers::h(Helpers::longUrl($config, (int) $editLink['e_length'])) ?>">
            </div>
            <div class="full actions">
              <button class="btn" type="submit">Save changes</button>
              <a class="btn ghost" href="<?= Helpers::h(Helpers::longUrl($config, (int) $editLink['e_length'])) ?>" target="_blank" rel="noopener">Open link</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <form class="search" method="get">
        <input type="hidden" name="view" value="links">
        <input type="text" name="q" value="<?= Helpers::h($q) ?>" placeholder="Search target URL or e length">
        <button class="btn" type="submit">Search</button>
      </form>

      <div class="card">
        <div class="section-head">
          <div><p class="eyebrow">Library</p><h3><?= (int) $list['total'] ?> links</h3></div>
        </div>
        <div class="table-wrap">
          <table class="table">
            <tr><th>ID</th><th>e length</th><th>Target</th><th>Clicks</th><th>Status</th><th>Expires</th><th>Actions</th></tr>
            <?php foreach ($list['rows'] as $row): ?>
              <?php
                $expired = !empty($row['expires_at']) && (string) $row['expires_at'] < Helpers::now();
                $status = !(int) $row['enabled'] ? 'Disabled' : ($expired ? 'Expired' : 'Active');
              ?>
              <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= (int) $row['e_length'] ?></td>
                <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                <td><?= (int) $row['clicks'] ?></td>
                <td><span class="badge <?= strtolower($status) ?>"><?= Helpers::h($status) ?></span></td>
                <td><?= Helpers::h((string) ($row['expires_at'] ?? 'Never')) ?></td>
                <td>
                  <div class="row-actions">
                    <a class="btn tiny ghost" href="/admin/?view=links&edit=<?= (int) $row['id'] ?>">Edit</a>
                    <form method="post">
                      <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <?php if ((int) $row['enabled']): ?>
                        <button class="btn tiny ghost" name="act" value="disable">Disable</button>
                      <?php else: ?>
                        <button class="btn tiny ghost" name="act" value="enable">Enable</button>
                      <?php endif; ?>
                      <button class="btn tiny danger" name="act" value="delete" onclick="return confirm('Delete this link and its click logs?')">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>

    <?php elseif ($view === 'analytics'): ?>
      <div class="card">
        <div class="section-head"><div><p class="eyebrow">Raw events</p><h3>Recent visits</h3></div></div>
        <div class="table-wrap">
          <table class="table">
            <tr><th>Time</th><th>Link</th><th>IP</th><th>Referer</th><th>URI</th><th>User agent</th></tr>
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
      </div>

    <?php elseif ($view === 'api'): ?>
      <div class="settings-grid">
        <div class="card">
          <p class="eyebrow">Endpoint</p>
          <h3>Create a long URL</h3>
          <p><code>POST /api/create</code></p>
          <p class="muted">Authorization header:</p>
          <pre class="codebox">Authorization: Bearer YOUR_TOKEN</pre>
          <p class="muted">Body fields: <code>url</code>, <code>length</code></p>
        </div>

        <div class="card">
          <p class="eyebrow">API secret</p>
          <h3>Current token</h3>
          <div class="secret-row">
            <code id="api-token"><?= Helpers::h((string) ($config['admin']['api_token'] ?? '')) ?></code>
            <button class="btn ghost tiny" type="button" onclick="navigator.clipboard.writeText(document.getElementById('api-token').textContent)">Copy</button>
          </div>
          <form method="post" class="actions" onsubmit="return confirm('Rotate the API token? Existing clients will stop working.')">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <button class="btn danger" name="act" value="rotate_api_token">Rotate token</button>
          </form>
        </div>
      </div>

    <?php elseif ($view === 'settings'): ?>
      <div class="settings-grid">
        <div class="card">
          <p class="eyebrow">Application</p>
          <h3>URL settings</h3>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="act" value="update_app_settings">
            <label>Base URL</label>
            <input type="url" name="base_url" value="<?= Helpers::h((string) ($config['app']['base_url'] ?? '')) ?>" required>
            <div class="form-grid compact">
              <div>
                <label>Minimum e length</label>
                <input type="number" name="min_length" min="1" max="10000" value="<?= (int) ($config['app']['min_length'] ?? 8) ?>" required>
              </div>
              <div>
                <label>Maximum e length</label>
                <input type="number" name="max_length" min="1" max="10000" value="<?= (int) ($config['app']['max_length'] ?? 5000) ?>" required>
              </div>
            </div>
            <div class="actions"><button class="btn" type="submit">Save settings</button></div>
          </form>
        </div>

        <div class="card">
          <p class="eyebrow">Security</p>
          <h3>Change admin password</h3>
          <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="act" value="change_admin_password">
            <label>Current password</label>
            <input type="password" name="current_password" autocomplete="current-password" required>
            <label style="margin-top:12px">New password</label>
            <input type="password" name="new_password" minlength="10" autocomplete="new-password" required>
            <label style="margin-top:12px">Confirm new password</label>
            <input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required>
            <div class="actions"><button class="btn" type="submit">Update password</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
