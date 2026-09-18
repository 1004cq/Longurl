<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['admin_ok']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Helpers::redirect('/admin/?view=login');
        }
    }

    public static function login(array $config, string $password, RateLimiter $limiter): bool
    {
        if (!$limiter->hit('login', (int) ($config['rate']['login_per_minute'] ?? 8))) {
            return false;
        }

        $hash = (string) ($config['admin']['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'] ?? '',
                (bool) $p['secure'],
                (bool) $p['httponly']
            );
        }

        session_destroy();
    }

    public static function apiTokenOk(array $config): bool
    {
        $expected = (string) ($config['admin']['api_token'] ?? '');
        if ($expected === '') {
            return false;
        }

        $token = '';
        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
            $token = trim($m[1]);
        }

        if ($token === '') {
            $token = trim((string) ($_SERVER['HTTP_X_API_TOKEN'] ?? ''));
        }

        return $token !== '' && hash_equals($expected, $token);
    }
}
