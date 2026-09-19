<?php
declare(strict_types=1);

final class Helpers
{
    private static ?array $requestData = null;

    public static function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function requestData(): array
    {
        if (self::$requestData !== null) {
            return self::$requestData;
        }

        if ($_POST !== []) {
            return self::$requestData = $_POST;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_starts_with($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            return self::$requestData = is_array($decoded) ? $decoded : [];
        }

        return self::$requestData = [];
    }

    public static function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $remote = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
        $remoteIsPublic = (bool) filter_var(
            $remote,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($remoteIsPublic) {
            return $remote;
        }

        foreach (['HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            $raw = (string) ($_SERVER[$header] ?? '');
            if ($raw === '') {
                continue;
            }
            $first = trim(explode(',', $raw)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $remote;
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    public static function isAllowedUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    public static function isEPath(string $path): bool
    {
        return $path !== '' && preg_match('/^e+$/', $path) === 1;
    }

    public static function requestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rawurldecode($path);
        return '/' . ltrim($path, '/');
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function baseUrl(array $config): string
    {
        return rtrim((string) ($config['app']['base_url'] ?? ''), '/');
    }

    public static function longUrl(array $config, int $length): string
    {
        return self::baseUrl($config) . '/' . str_repeat('e', $length);
    }

    public static function assetUrl(string $asset): string
    {
        $asset = ltrim($asset, '/');
        $path = BASE_PATH . '/public/assets/' . $asset;
        $version = is_file($path) ? (string) filemtime($path) : '1';
        return '/assets/' . $asset . '?v=' . rawurlencode($version);
    }
}
