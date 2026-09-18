<?php
declare(strict_types=1);

final class RateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function hit(string $bucket, int $limitPerMinute): bool
    {
        $ip = Helpers::clientIp();
        $window = date('Y-m-d H:i:00');
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (bucket, ip, hits, window_start)
             VALUES (:bucket, :ip, 1, :window)
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        );
        $stmt->execute([
            ':bucket' => $bucket,
            ':ip' => $ip,
            ':window' => $window,
        ]);
        $q = $this->pdo->prepare(
            'SELECT hits FROM rate_limits WHERE bucket = :bucket AND ip = :ip AND window_start = :window'
        );
        $q->execute([
            ':bucket' => $bucket,
            ':ip' => $ip,
            ':window' => $window,
        ]);
        $hits = (int) $q->fetchColumn();
        if (random_int(1, 30) === 1) {
            $this->pdo->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        }
        return $hits <= $limitPerMinute;
    }
}
