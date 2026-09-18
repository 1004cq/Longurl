<?php
declare(strict_types=1);

/** @var string $view */
/** @var array $stats */
/** @var int $period */
/** @var int $periodClickCount */
/** @var array $dailyClicks */
/** @var array $topLinks */
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
    'dashboard' => I18n::t('dashboard'),
    'links' => I18n::t('links'),
    'analytics' => I18n::t('analytics'),
    'api' => I18n::t('api'),
    'settings' => I18n::t('settings'),
];

$maxDaily = 1;
foreach ($dailyClicks as $point) {
    $maxDaily = max($maxDaily, (int) $point['clicks']);
}

$queryForPage = [
    'view' => 'links',
    'q' => $q,
];
?><!doctype html>
<html lang="<?= Helpers::h(I18n::locale()) ?>">
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

    <div class="side-bottom">
      <div class="lang-switch admin-lang">
        <a class="<?= I18n::locale() === 'en' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('en')) ?>">EN</a>
        <a class="<?= I18n::locale() === 'zh' ? 'active' : '' ?>" href="<?= Helpers::h(I18n::switchUrl('zh')) ?>">中文</a>
      </div>
      <a class="logout" href="/admin/?view=logout"><?= Helpers::h(I18n::t('logout')) ?></a>
    </div>
  </aside>

  <main class="main">
    <div class="admin-top">
      <div>
        <p class="eyebrow">EEEE Console</p>
        <h1><?= Helpers::h($nav[$view] ?? I18n::t('dashboard')) ?></h1>
      </div>
      <a class="btn ghost" href="/" target="_blank" rel="noopener"><?= Helpers::h(I18n::t('open_site')) ?></a>
    </div>

    <?php if ($notice !== ''): ?><div class="notice"><?= Helpers::h($notice) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert"><?= Helpers::h($error) ?></div><?php endif; ?>

    <?php if ($view === 'dashboard'): ?>
      <div class="stats stats-4">
        <div class="card stat"><span class="muted"><?= Helpers::h(I18n::t('links')) ?></span><b><?= (int) $stats['links'] ?></b></div>
        <div class="card stat"><span class="muted"><?= Helpers::h(I18n::t('active')) ?></span><b><?= (int) $stats['active'] ?></b></div>
        <div class="card stat"><span class="muted"><?= Helpers::h(I18n::t('clicks')) ?></span><b><?= (int) $stats['clicks'] ?></b></div>
        <div class="card stat"><span class="muted"><?= Helpers::h(I18n::t('today')) ?></span><b><?= (int) $stats['today'] ?></b></div>
      </div>

      <div class="card">
        <div class="section-head">
          <div>
            <p class="eyebrow"><?= Helpers::h(I18n::t('traffic')) ?></p>
            <h3><?= Helpers::h($period === 30 ? I18n::t('last_30_days') : I18n::t('last_7_days')) ?> · <?= (int) $periodClickCount ?> <?= Helpers::h(I18n::t('clicks')) ?></h3>
          </div>
          <div class="segmented">
            <a class="<?= $period === 7 ? 'active' : '' ?>" href="/admin/?view=dashboard&period=7">7D</a>
            <a class="<?= $period === 30 ? 'active' : '' ?>" href="/admin/?view=dashboard&period=30">30D</a>
          </div>
        </div>

        <div class="bars <?= $period === 30 ? 'bars-30' : '' ?>" aria-label="Traffic chart">
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
          <div class="section-head">
            <h3><?= Helpers::h(I18n::t('top_links')) ?></h3>
            <span class="muted"><?= Helpers::h($period === 30 ? I18n::t('last_30_days') : I18n::t('last_7_days')) ?></span>
          </div>
          <div class="table-wrap">
            <table class="table">
              <tr><th>e</th><th><?= Helpers::h(I18n::t('target_url')) ?></th><th><?= Helpers::h(I18n::t('clicks')) ?></th></tr>
              <?php foreach ($topLinks as $row): ?>
                <tr>
                  <td><span class="badge"><?= (int) $row['e_length'] ?></span></td>
                  <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                  <td><?= (int) $row['period_clicks'] ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$topLinks): ?>
                <tr><td colspan="3" class="muted">—</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="section-head"><h3><?= Helpers::h(I18n::t('recent_links')) ?></h3><a class="text-link" href="/admin/?view=links"><?= Helpers::h(I18n::t('view_all')) ?></a></div>
          <div class="table-wrap">
            <table class="table">
              <tr><th>e</th><th><?= Helpers::h(I18n::t('target_url')) ?></th><th><?= Helpers::h(I18n::t('clicks')) ?></th></tr>
              <?php foreach (array_slice($recentLinks, 0, 8) as $row): ?>
                <tr>
                  <td><span class="badge"><?= (int) $row['e_length'] ?></span></td>
                  <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                  <td><?= (int) $row['clicks'] ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:14px">
        <div class="section-head"><h3><?= Helpers::h(I18n::t('recent_visits')) ?></h3><a class="text-link" href="/admin/?view=analytics"><?= Helpers::h(I18n::t('view_all')) ?></a></div>
        <div class="table-wrap">
          <table class="table">
            <tr><th>Time</th><th>e</th><th>IP</th><th><?= Helpers::h(I18n::t('target_url')) ?></th></tr>
            <?php foreach (array_slice($recentClicks, 0, 10) as $row): ?>
              <tr>
                <td><?= Helpers::h($row['clicked_at']) ?></td>
                <td><?= (int) $row['e_length'] ?></td>
                <td><?= Helpers::h($row['ip']) ?></td>
                <td class="break"><?= Helpers::h($row['target_url']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>

    <?php elseif ($view === 'links'): ?>
      <?php if ($editLink): ?>
        <div class="card edit-card">
          <div class="section-head">
            <div>
              <p class="eyebrow"><?= Helpers::h(I18n::t('edit_link')) ?></p>
              <h3><?= (int) $editLink['e_length'] ?> e</h3>
            </div>
            <a class="text-link" href="/admin/?view=links">×</a>
          </div>

          <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $editLink['id'] ?>">
            <input type="hidden" name="act" value="update_link">

            <div class="full">
              <label><?= Helpers::h(I18n::t('target_url')) ?></label>
              <input type="url" name="target_url" value="<?= Helpers::h($editLink['target_url']) ?>" required>
            </div>

            <div>
              <label><?= Helpers::h(I18n::t('expires_at')) ?></label>
              <?php
                $expiresValue = '';
                if (!empty($editLink['expires_at'])) {
                    $expiresValue = date('Y-m-d\TH:i', strtotime((string) $editLink['expires_at']));
                }
              ?>
              <input type="datetime-local" name="expires_at" value="<?= Helpers::h($expiresValue) ?>">
            </div>

            <div>
              <label><?= Helpers::h(I18n::t('long_url')) ?></label>
              <input type="text" readonly value="<?= Helpers::h(Helpers::longUrl($config, (int) $editLink['e_length'])) ?>">
            </div>

            <div class="full actions">
              <button class="btn" type="submit"><?= Helpers::h(I18n::t('save_changes')) ?></button>
              <a class="btn ghost" href="<?= Helpers::h(Helpers::longUrl($config, (int) $editLink['e_length'])) ?>" target="_blank" rel="noopener"><?= Helpers::h(I18n::t('open')) ?></a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <div class="toolbar">
        <form class="search" method="get">
          <input type="hidden" name="view" value="links">
          <input type="text" name="q" value="<?= Helpers::h($q) ?>" placeholder="<?= Helpers::h(I18n::t('search_placeholder')) ?>">
          <button class="btn" type="submit"><?= Helpers::h(I18n::t('search')) ?></button>
        </form>

        <a class="btn ghost" href="/admin/?view=links&export=links&q=<?= urlencode($q) ?>"><?= Helpers::h(I18n::t('export_csv')) ?></a>
      </div>

      <div class="card">
        <div class="section-head">
          <div><p class="eyebrow">Library</p><h3><?= (int) $list['total'] ?> <?= Helpers::h(I18n::t('links')) ?></h3></div>
          <div class="muted">Page <?= (int) $list['page'] ?> / <?= (int) $list['pages'] ?></div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <tr>
              <th>ID</th>
              <th>e</th>
              <th><?= Helpers::h(I18n::t('target_url')) ?></th>
              <th><?= Helpers::h(I18n::t('clicks')) ?></th>
              <th><?= Helpers::h(I18n::t('status')) ?></th>
              <th><?= Helpers::h(I18n::t('expires')) ?></th>
              <th><?= Helpers::h(I18n::t('actions')) ?></th>
            </tr>
            <?php foreach ($list['rows'] as $row): ?>
              <?php
                $expired = !empty($row['expires_at']) && (string) $row['expires_at'] < Helpers::now();
                $statusKey = !(int) $row['enabled'] ? 'disabled' : ($expired ? 'expired' : 'active');
              ?>
              <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><?= (int) $row['e_length'] ?></td>
                <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                <td><?= (int) $row['clicks'] ?></td>
                <td><span class="badge <?= Helpers::h($statusKey) ?>"><?= Helpers::h(I18n::t($statusKey)) ?></span></td>
                <td><?= Helpers::h(!empty($row['expires_at']) ? (string) $row['expires_at'] : I18n::t('never')) ?></td>
                <td>
                  <div class="row-actions">
                    <a class="btn tiny ghost" href="/admin/?view=links&edit=<?= (int) $row['id'] ?>"><?= Helpers::h(I18n::t('edit')) ?></a>
                    <form method="post">
                      <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <?php if ((int) $row['enabled']): ?>
                        <button class="btn tiny ghost" name="act" value="disable"><?= Helpers::h(I18n::t('disable')) ?></button>
                      <?php else: ?>
                        <button class="btn tiny ghost" name="act" value="enable"><?= Helpers::h(I18n::t('enable')) ?></button>
                      <?php endif; ?>
                      <button class="btn tiny danger" name="act" value="delete" onclick="return confirm('Delete this link and its click logs?')"><?= Helpers::h(I18n::t('delete')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <?php if ((int) $list['pages'] > 1): ?>
          <div class="pagination">
            <?php if ((int) $list['page'] > 1): ?>
              <?php $prev = $queryForPage + ['page' => (int) $list['page'] - 1]; ?>
              <a class="btn tiny ghost" href="/admin/?<?= Helpers::h(http_build_query($prev)) ?>"><?= Helpers::h(I18n::t('previous')) ?></a>
            <?php endif; ?>

            <span class="muted"><?= (int) $list['page'] ?> / <?= (int) $list['pages'] ?></span>

            <?php if ((int) $list['page'] < (int) $list['pages']): ?>
              <?php $next = $queryForPage + ['page' => (int) $list['page'] + 1]; ?>
              <a class="btn tiny ghost" href="/admin/?<?= Helpers::h(http_build_query($next)) ?>"><?= Helpers::h(I18n::t('next')) ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($view === 'analytics'): ?>
      <div class="toolbar">
        <div class="segmented">
          <a class="<?= $period === 7 ? 'active' : '' ?>" href="/admin/?view=analytics&period=7">7D</a>
          <a class="<?= $period === 30 ? 'active' : '' ?>" href="/admin/?view=analytics&period=30">30D</a>
        </div>
        <a class="btn ghost" href="/admin/?view=analytics&export=clicks"><?= Helpers::h(I18n::t('export_csv')) ?></a>
      </div>

      <div class="card" style="margin-bottom:14px">
        <div class="section-head">
          <div><p class="eyebrow"><?= Helpers::h(I18n::t('top_links')) ?></p><h3><?= Helpers::h($period === 30 ? I18n::t('last_30_days') : I18n::t('last_7_days')) ?></h3></div>
          <strong><?= (int) $periodClickCount ?> <?= Helpers::h(I18n::t('clicks')) ?></strong>
        </div>
        <div class="table-wrap">
          <table class="table">
            <tr><th>e</th><th><?= Helpers::h(I18n::t('target_url')) ?></th><th><?= Helpers::h(I18n::t('clicks')) ?></th></tr>
            <?php foreach ($topLinks as $row): ?>
              <tr>
                <td><?= (int) $row['e_length'] ?></td>
                <td class="break"><?= Helpers::h($row['target_url']) ?></td>
                <td><?= (int) $row['period_clicks'] ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="section-head"><div><p class="eyebrow"><?= Helpers::h(I18n::t('raw_events')) ?></p><h3><?= Helpers::h(I18n::t('recent_visits')) ?></h3></div></div>
        <div class="table-wrap">
          <table class="table">
            <tr><th>Time</th><th>e</th><th>IP</th><th>Referer</th><th>URI</th><th>User agent</th></tr>
            <?php foreach ($recentClicks as $row): ?>
              <tr>
                <td><?= Helpers::h($row['clicked_at']) ?></td>
                <td><?= (int) $row['e_length'] ?></td>
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
          <p class="eyebrow"><?= Helpers::h(I18n::t('endpoint')) ?></p>
          <h3>POST /api/create</h3>
          <p class="muted">Authorization:</p>
          <pre class="codebox">Authorization: Bearer YOUR_TOKEN</pre>
          <p class="muted">Body: <code>url</code>, <code>length</code></p>
        </div>

        <div class="card">
          <p class="eyebrow"><?= Helpers::h(I18n::t('api_secret')) ?></p>
          <h3><?= Helpers::h(I18n::t('current_token')) ?></h3>
          <div class="secret-row">
            <code id="api-token"><?= Helpers::h((string) ($config['admin']['api_token'] ?? '')) ?></code>
            <button class="btn ghost tiny" type="button" onclick="navigator.clipboard.writeText(document.getElementById('api-token').textContent)"><?= Helpers::h(I18n::t('copy')) ?></button>
          </div>

          <form method="post" class="actions" onsubmit="return confirm('Rotate the API token? Existing clients will stop working.')">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <button class="btn danger" name="act" value="rotate_api_token"><?= Helpers::h(I18n::t('rotate_token')) ?></button>
          </form>
        </div>
      </div>

    <?php elseif ($view === 'settings'): ?>
      <div class="settings-grid">
        <div class="card">
          <p class="eyebrow"><?= Helpers::h(I18n::t('application')) ?></p>
          <h3><?= Helpers::h(I18n::t('url_settings')) ?></h3>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="act" value="update_app_settings">

            <label><?= Helpers::h(I18n::t('base_url')) ?></label>
            <input type="url" name="base_url" value="<?= Helpers::h((string) ($config['app']['base_url'] ?? '')) ?>" required>

            <div class="form-grid compact">
              <div>
                <label><?= Helpers::h(I18n::t('min_e_length')) ?></label>
                <input type="number" name="min_length" min="1" max="10000" value="<?= (int) ($config['app']['min_length'] ?? 8) ?>" required>
              </div>
              <div>
                <label><?= Helpers::h(I18n::t('max_e_length')) ?></label>
                <input type="number" name="max_length" min="1" max="10000" value="<?= (int) ($config['app']['max_length'] ?? 5000) ?>" required>
              </div>
            </div>

            <div class="actions"><button class="btn" type="submit"><?= Helpers::h(I18n::t('save_settings')) ?></button></div>
          </form>
        </div>

        <div class="card">
          <p class="eyebrow"><?= Helpers::h(I18n::t('security')) ?></p>
          <h3><?= Helpers::h(I18n::t('change_password')) ?></h3>

          <form method="post" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= Helpers::h($csrf) ?>">
            <input type="hidden" name="act" value="change_admin_password">

            <label><?= Helpers::h(I18n::t('current_password')) ?></label>
            <input type="password" name="current_password" autocomplete="current-password" required>

            <label style="margin-top:12px"><?= Helpers::h(I18n::t('new_password')) ?></label>
            <input type="password" name="new_password" minlength="10" autocomplete="new-password" required>

            <label style="margin-top:12px"><?= Helpers::h(I18n::t('confirm_password')) ?></label>
            <input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required>

            <div class="actions"><button class="btn" type="submit"><?= Helpers::h(I18n::t('update_password')) ?></button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
