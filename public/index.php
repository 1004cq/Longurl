<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$path = Helpers::requestPath();

if (!app_installed()) {
    if (str_starts_with($path, '/install')) {
        require __DIR__ . '/install/index.php';
        exit;
    }
    Helpers::redirect('/install/');
}

app_boot_prod();

try {
    $config = app_config();
    $pdo = Database::pdo($config);
    $links = new LinkService($pdo, $config);
    $limiter = new RateLimiter($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Service unavailable.';
    exit;
}

if ($path === '/api/create' || $path === '/api/create/') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        Helpers::json(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    if (!Auth::apiTokenOk($config)) {
        Helpers::json(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if (!$limiter->hit('api', (int) ($config['rate']['api_per_minute'] ?? 60))) {
        Helpers::json(['success' => false, 'error' => 'Rate limited'], 429);
    }
    try {
        $data = Helpers::requestData();
        $url = (string) ($data['url'] ?? '');
        $length = (int) ($data['length'] ?? 50);
        $created = $links->create($url, $length);
        Helpers::json(['success' => true, 'url' => $created['url'], 'length' => $created['length'], 'target' => $created['target']]);
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) {
            Helpers::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
        error_log('API create failed: ' . $e->getMessage());
        Helpers::json(['success' => false, 'error' => 'Unable to create link right now.'], 500);
    }
}

if (str_starts_with($path, '/admin')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

if (($_GET['action'] ?? '') === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf($_POST['_csrf'] ?? null)) {
        Helpers::json(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }
    if (!$limiter->hit('create', (int) ($config['rate']['create_per_minute'] ?? 20))) {
        Helpers::json(['success' => false, 'error' => 'Too many requests'], 429);
    }
    try {
        $data = Helpers::requestData();
        $created = $links->create((string) ($data['url'] ?? ''), (int) ($data['length'] ?? 50));
        Helpers::json(['success' => true, 'url' => $created['url'], 'length' => $created['length'], 'target' => $created['target']]);
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) {
            Helpers::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
        error_log('Public create failed: ' . $e->getMessage());
        Helpers::json(['success' => false, 'error' => 'Unable to create link right now.'], 500);
    }
}

$trim = trim($path, '/');
if ($trim !== '' && Helpers::isEPath($trim)) {
    $link = $links->findByLength(strlen($trim));
    if (!$link || !(int) $link['enabled']) {
        http_response_code(404);
        $title = '404';
        $page = 'lost';
        require dirname(__DIR__) . '/src/views/home.php';
        exit;
    }
    if (!empty($link['expires_at']) && $link['expires_at'] < Helpers::now()) {
        http_response_code(404);
        $title = '404';
        $page = 'lost';
        require dirname(__DIR__) . '/src/views/home.php';
        exit;
    }
    try {
        $links->recordClick($link);
    } catch (Throwable $e) {
        // still redirect
    }
    Helpers::redirect($link['target_url'], 302);
}

if ($trim !== '' && $trim !== 'index.php') {
    http_response_code(404);
    $title = '404';
    $page = 'lost';
    require dirname(__DIR__) . '/src/views/home.php';
    exit;
}

$title = 'EEEE — Long URL Generator';
$page = 'home';
require dirname(__DIR__) . '/src/views/home.php';
