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

$view = (string) ($_GET['view'] ?? 'dashboard');
$error = '';

if ($view === 'logout') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $act = (string) ($_POST['act'] ?? '');
        if ($id > 0) {
            if ($act === 'disable') {
                $links->setEnabled($id, false);
            } elseif ($act === 'enable') {
                $links->setEnabled($id, true);
            } elseif ($act === 'delete') {
                $links->delete($id);
            }
        }
        Helpers::redirect('/admin/?view=' . urlencode($view));
    }
}

$stats = $links->stats();
$recentLinks = $links->recentLinks();
$recentClicks = $links->recentClicks();
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$list = $links->searchLinks($q, $pageNo, 20);
$csrf = Helpers::csrfToken();

require dirname(__DIR__, 2) . '/src/views/admin.php';
