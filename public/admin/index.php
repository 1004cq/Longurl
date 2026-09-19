<?php
declare(strict_types=1);

if (!isset($config, $pdo, $links, $limiter)) {
    require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

    if (!app_installed()) {
        Helpers::redirect('/install/');
    }

    app_boot_prod();
    $config = app_config();
    $pdo = Database::pdo($config);
    $links = new LinkService($pdo, $config);
    $limiter = new RateLimiter($pdo);
}

function saveLocalConfig(array $config): void
{
    $content = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";

    if (file_put_contents(CONFIG_FILE, $content, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save config/local.php.');
    }

    @chmod(CONFIG_FILE, 0640);
}

function outputCsv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }

    fclose($out);
    exit;
}

$view = (string) ($_GET['view'] ?? 'dashboard');
$error = '';
$notice = trim((string) ($_GET['notice'] ?? ''));

$allowedViews = ['dashboard', 'links', 'analytics', 'api', 'settings', 'login', 'logout'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'dashboard';
}

if ($view === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helpers::verifyCsrf($_POST['_csrf'] ?? null)) {
        Helpers::redirect('/admin/?view=login');
    }
    Auth::logout();
    Helpers::redirect('/admin/?view=login');
}

if ($view === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Helpers::verifyCsrf($_POST['_csrf'] ?? null)) {
            $error = 'Invalid CSRF token.';
        } elseif (!Auth::login($config, (string) ($_POST['password'] ?? ''), $limiter)) {
            $error = 'Login failed.';
        } else {
            Helpers::redirect('/admin/');
        }
    }

    $title = 'Admin login';
    require dirname(__DIR__, 2) . '/src/views/admin_login.php';
    exit;
}

Auth::requireLogin();

$export = (string) ($_GET['export'] ?? '');
if ($export === 'links') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $rows = array_map(
        static fn(array $row): array => [
            $row['id'],
            $row['e_length'],
            $row['target_url'],
            $row['clicks'],
            $row['enabled'],
            $row['created_at'],
            $row['updated_at'],
            $row['expires_at'] ?? '',
        ],
        $links->exportLinks($q)
    );

    outputCsv(
        'eeee-links-' . date('Ymd-His') . '.csv',
        ['id', 'e_length', 'target_url', 'clicks', 'enabled', 'created_at', 'updated_at', 'expires_at'],
        $rows
    );
}

if ($export === 'clicks') {
    $rows = array_map(
        static fn(array $row): array => [
            $row['id'],
            $row['clicked_at'],
            $row['e_length'],
            $row['target_url'],
            $row['ip'],
            $row['referer'],
            $row['request_uri'],
            $row['user_agent'],
        ],
        $links->exportClicks()
    );

    outputCsv(
        'eeee-clicks-' . date('Ymd-His') . '.csv',
        ['id', 'clicked_at', 'e_length', 'target_url', 'ip', 'referer', 'request_uri', 'user_agent'],
        $rows
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $act = (string) ($_POST['act'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        try {
            if ($act === 'disable' && $id > 0) {
                $links->setEnabled($id, false);
                Helpers::redirect('/admin/?view=links&notice=' . urlencode('Link disabled.'));
            }

            if ($act === 'enable' && $id > 0) {
                $links->setEnabled($id, true);
                Helpers::redirect('/admin/?view=links&notice=' . urlencode('Link enabled.'));
            }

            if ($act === 'delete' && $id > 0) {
                $links->delete($id);
                Helpers::redirect('/admin/?view=links&notice=' . urlencode('Link deleted.'));
            }

            if ($act === 'update_link' && $id > 0) {
                $links->updateLink(
                    $id,
                    (string) ($_POST['target_url'] ?? ''),
                    trim((string) ($_POST['expires_at'] ?? '')) ?: null
                );
                Helpers::redirect('/admin/?view=links&notice=' . urlencode('Link updated.'));
            }

            if ($act === 'rotate_api_token') {
                $config['admin']['api_token'] = Helpers::randomToken(32);
                saveLocalConfig($config);
                Helpers::redirect('/admin/?view=api&notice=' . urlencode('API token rotated.'));
            }

            if ($act === 'change_admin_password') {
                $current = (string) ($_POST['current_password'] ?? '');
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['confirm_password'] ?? '');
                $hash = (string) ($config['admin']['password_hash'] ?? '');

                if ($hash === '' || !password_verify($current, $hash)) {
                    throw new InvalidArgumentException('Current password is incorrect.');
                }
                if (strlen($new) < 10) {
                    throw new InvalidArgumentException('New password must be at least 10 characters.');
                }
                if (!hash_equals($new, $confirm)) {
                    throw new InvalidArgumentException('New passwords do not match.');
                }

                $newHash = password_hash($new, PASSWORD_DEFAULT);
                if ($newHash === false) {
                    throw new RuntimeException('Unable to hash the new password.');
                }

                $config['admin']['password_hash'] = $newHash;
                saveLocalConfig($config);
                session_regenerate_id(true);
                Helpers::redirect('/admin/?view=settings&notice=' . urlencode('Admin password updated.'));
            }

            if ($act === 'update_app_settings') {
                $base = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
                $minLength = (int) ($_POST['min_length'] ?? 8);
                $maxLength = (int) ($_POST['max_length'] ?? 5000);
                $parts = parse_url($base);

                $validBase = filter_var($base, FILTER_VALIDATE_URL) !== false
                    && is_array($parts)
                    && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                    && !empty($parts['host'])
                    && (($parts['path'] ?? '') === '' || ($parts['path'] ?? '') === '/')
                    && empty($parts['query'])
                    && empty($parts['fragment']);

                if (!$validBase) {
                    throw new InvalidArgumentException('Base URL must be a root http/https URL.');
                }
                if ($minLength < 1 || $maxLength < $minLength || $maxLength > 10000) {
                    throw new InvalidArgumentException('Invalid minimum/maximum length.');
                }

                $config['app']['base_url'] = $base;
                $config['app']['min_length'] = $minLength;
                $config['app']['max_length'] = $maxLength;
                saveLocalConfig($config);
                Helpers::redirect('/admin/?view=settings&notice=' . urlencode('App settings saved.'));
            }
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Operation failed. Check server logs.';
        }
    }
}

$period = (int) ($_GET['period'] ?? 7);
$period = in_array($period, [7, 30], true) ? $period : 7;

$stats = ['links' => 0, 'clicks' => 0, 'today' => 0, 'active' => 0];
$periodClickCount = 0;
$dailyClicks = [];
$topLinks = [];
$recentLinks = [];
$recentClicks = [];
if ($view === 'dashboard' || $view === 'analytics') {
    $periodClickCount = $links->periodClicks($period);
    $dailyClicks = $links->dailyClicks($period);
    $topLinks = $links->topLinks($period, 10);
}
if ($view === 'dashboard') {
    $stats = $links->stats();
    $recentLinks = $links->recentLinks();
    $recentClicks = $links->recentClicks(50);
} elseif ($view === 'analytics') {
    $recentClicks = $links->recentClicks(50);
}
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$list = $view === 'links'
    ? $links->searchLinks($q, $pageNo, 20)
    : ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$csrf = Helpers::csrfToken();

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editLink = $editId > 0 ? $links->find($editId) : null;

require dirname(__DIR__, 2) . '/src/views/admin.php';
