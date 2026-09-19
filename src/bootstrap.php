<?php
declare(strict_types=1);

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_FILE', BASE_PATH . '/config/local.php');
define('LOCK_FILE', BASE_PATH . '/storage/installed.lock');

require_once BASE_PATH . '/src/Helpers.php';
require_once BASE_PATH . '/src/Database.php';
require_once BASE_PATH . '/src/Auth.php';
require_once BASE_PATH . '/src/I18n.php';
require_once BASE_PATH . '/src/RateLimiter.php';
require_once BASE_PATH . '/src/LinkService.php';

function app_installed(): bool
{
    return is_file(CONFIG_FILE) && is_file(LOCK_FILE);
}

function app_config(): array
{
    if (!is_file(CONFIG_FILE)) {
        throw new RuntimeException('Missing configuration.');
    }

    $config = require CONFIG_FILE;
    if (!is_array($config)) {
        throw new RuntimeException('Invalid configuration.');
    }

    return $config;
}

function app_boot_prod(): void
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    Helpers::sendSecurityHeaders();
}
Auth::start();
I18n::init();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
Helpers::sendSecurityHeaders();
