<?php
declare(strict_types=1);

final class LinkService
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function create(string $url, int $wantedLength): array
    {
        $url = Helpers::normalizeUrl($url);
        if (!Helpers::isAllowedUrl($url)) {
            throw new InvalidArgumentException('Invalid URL. Only http/https public URLs are allowed.');
        }

        $min = (int) ($this->config['app']['min_length'] ?? 8);
        $max = (int) ($this->config['app']['max_length'] ?? 5000);

        if ($wantedLength < $min || $wantedLength > $max) {
            throw new InvalidArgumentException("Length must be between {$min} and {$max}.");
        }

        $candidate = $wantedLength;

        while ($candidate <= $max) {
            $length = $this->nextFreeLength($candidate, $max);
            $now = Helpers::now();

            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO links (e_length, target_url, clicks, enabled, created_at, updated_at)
                     VALUES (:len, :url, 0, 1, :created_at, :updated_at)'
                );
                $stmt->execute([
                    ':len' => $length,
                    ':url' => $url,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                return [
                    'id' => (int) $this->pdo->lastInsertId(),
                    'length' => $length,
                    'target' => $url,
                    'url' => Helpers::longUrl($this->config, $length),
                ];
            } catch (PDOException $e) {
                if (!$this->isDuplicateKey($e)) {
                    throw $e;
                }

                $candidate = $length + 1;
            }
        }

        throw new RuntimeException('No free e-length available in the allowed range.');
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $info = $e->errorInfo;
        $sqlState = (string) ($info[0] ?? $e->getCode());
        $driverCode = (int) ($info[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }

    public function nextFreeLength(int $start, int $max): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT e_length
             FROM links
             WHERE e_length >= :start AND e_length <= :max
             ORDER BY e_length ASC'
        );
        $stmt->execute([
            ':start' => $start,
            ':max' => $max,
        ]);

        $candidate = $start;
        while ($row = $stmt->fetch()) {
            $used = (int) $row['e_length'];

            if ($used < $candidate) {
                continue;
            }

            if ($used > $candidate) {
                return $candidate;
            }

            $candidate++;
            if ($candidate > $max) {
                break;
            }
        }

        if ($candidate <= $max) {
            return $candidate;
        }

        throw new RuntimeException('No free e-length available in the allowed range.');
    }

    public function findByLength(int $length): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM links WHERE e_length = :len LIMIT 1');
        $stmt->execute([':len' => $length]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function recordClick(array $link): void
    {
        $this->pdo->beginTransaction();

        try {
            $upd = $this->pdo->prepare(
                'UPDATE links SET clicks = clicks + 1, updated_at = :now WHERE id = :id'
            );
            $upd->execute([
                ':now' => Helpers::now(),
                ':id' => $link['id'],
            ]);

            $ins = $this->pdo->prepare(
                'INSERT INTO click_logs (link_id, clicked_at, ip, user_agent, referer, request_uri)
                 VALUES (:lid, :at, :ip, :ua, :ref, :uri)'
            );
            $ins->execute([
                ':lid' => $link['id'],
                ':at' => Helpers::now(),
                ':ip' => Helpers::clientIp(),
                ':ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                ':ref' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 1024),
                ':uri' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 2048),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function stats(): array
    {
        $totalLinks = (int) $this->pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        $totalClicks = (int) $this->pdo->query('SELECT COALESCE(SUM(clicks),0) FROM links')->fetchColumn();
        $today = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM click_logs WHERE clicked_at >= CURDATE()"
        )->fetchColumn();
        $active = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM links WHERE enabled = 1 AND (expires_at IS NULL OR expires_at >= NOW())"
        )->fetchColumn();

        return [
            'links' => $totalLinks,
            'clicks' => $totalClicks,
            'today' => $today,
            'active' => $active,
        ];
    }

    public function periodClicks(int $days): int
    {
        $days = max(1, min($days, 90));
        $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM click_logs WHERE clicked_at >= :start');
        $stmt->execute([':start' => $start->format('Y-m-d 00:00:00')]);

        return (int) $stmt->fetchColumn();
    }

    public function dailyClicks(int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');

        $stmt = $this->pdo->prepare(
            'SELECT DATE(clicked_at) AS day, COUNT(*) AS clicks
             FROM click_logs
             WHERE clicked_at >= :start
             GROUP BY DATE(clicked_at)
             ORDER BY day ASC'
        );
        $stmt->execute([
            ':start' => $start->format('Y-m-d 00:00:00'),
        ]);

        $byDay = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDay[(string) $row['day']] = (int) $row['clicks'];
        }

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+{$i} days")->format('Y-m-d');
            $result[] = [
                'day' => $day,
                'clicks' => $byDay[$day] ?? 0,
            ];
        }

        return $result;
    }

    public function topLinks(int $days = 7, int $limit = 10): array
    {
        $days = max(1, min($days, 90));
        $limit = max(1, min($limit, 50));
        $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');

        $stmt = $this->pdo->prepare(
            'SELECT l.id, l.e_length, l.target_url, COUNT(c.id) AS period_clicks
             FROM links l
             JOIN click_logs c ON c.link_id = l.id
             WHERE c.clicked_at >= :start
             GROUP BY l.id, l.e_length, l.target_url
             ORDER BY period_clicks DESC, l.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':start', $start->format('Y-m-d 00:00:00'));
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recentLinks(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM links ORDER BY id DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recentClicks(int $limit = 30): array
    {
        $limit = max(1, min($limit, 500));

        $sql = 'SELECT c.*, l.e_length, l.target_url
                FROM click_logs c
                JOIN links l ON l.id = c.link_id
                ORDER BY c.id DESC
                LIMIT :lim';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchLinks(string $q, int $page = 1, int $per = 20): array
    {
        $per = max(5, min($per, 100));
        $offset = max(0, ($page - 1) * $per);
        $where = '';
        $params = [];

        if ($q !== '') {
            $where = 'WHERE target_url LIKE :q OR CAST(e_length AS CHAR) = :exact';
            $params[':q'] = '%' . $q . '%';
            $params[':exact'] = $q;
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM links {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $maxPage = max(1, (int) ceil($total / $per));
        $page = min(max(1, $page), $maxPage);
        $offset = ($page - 1) * $per;

        $sql = "SELECT * FROM links {$where} ORDER BY id DESC LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->bindValue(':lim', $per, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per' => $per,
            'pages' => $maxPage,
        ];
    }

    public function exportLinks(string $q = '', int $limit = 10000): array
    {
        $limit = max(1, min($limit, 50000));
        $where = '';
        $params = [];

        if ($q !== '') {
            $where = 'WHERE target_url LIKE :q OR CAST(e_length AS CHAR) = :exact';
            $params[':q'] = '%' . $q . '%';
            $params[':exact'] = $q;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, e_length, target_url, clicks, enabled, created_at, updated_at, expires_at
             FROM links {$where}
             ORDER BY id DESC
             LIMIT :lim"
        );

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function exportClicks(int $limit = 10000): array
    {
        $limit = max(1, min($limit, 50000));

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.clicked_at, c.ip, c.user_agent, c.referer, c.request_uri,
                    l.e_length, l.target_url
             FROM click_logs c
             JOIN links l ON l.id = c.link_id
             ORDER BY c.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function updateLink(int $id, string $targetUrl, ?string $expiresAt): void
    {
        $targetUrl = Helpers::normalizeUrl($targetUrl);
        if (!Helpers::isAllowedUrl($targetUrl)) {
            throw new InvalidArgumentException('Invalid URL. Only http/https public URLs are allowed.');
        }

        if ($expiresAt !== null && $expiresAt !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiresAt);
            if (!$dt) {
                throw new InvalidArgumentException('Invalid expiration date.');
            }
            $expiresAt = $dt->format('Y-m-d H:i:s');
        } else {
            $expiresAt = null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE links
             SET target_url = :url, expires_at = :expires, updated_at = :now
             WHERE id = :id'
        );
        $stmt->bindValue(':url', $targetUrl);
        if ($expiresAt === null) {
            $stmt->bindValue(':expires', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':expires', $expiresAt);
        }
        $stmt->bindValue(':now', Helpers::now());
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE links SET enabled = :en, updated_at = :now WHERE id = :id'
        );
        $stmt->execute([
            ':en' => $enabled ? 1 : 0,
            ':now' => Helpers::now(),
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
