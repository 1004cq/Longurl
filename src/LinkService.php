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

        $length = $this->nextFreeLength($wantedLength, $max);
        $now = Helpers::now();
        $stmt = $this->pdo->prepare(
            'INSERT INTO links (e_length, target_url, clicks, enabled, created_at, updated_at)
             VALUES (:len, :url, 0, 1, :now, :now)'
        );
        $stmt->execute([
            ':len' => $length,
            ':url' => $url,
            ':now' => $now,
        ]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'length' => $length,
            'target' => $url,
            'url' => Helpers::longUrl($this->config, $length),
        ];
    }

    public function nextFreeLength(int $start, int $max): int
    {
        $stmt = $this->pdo->prepare('SELECT e_length FROM links WHERE e_length >= :start ORDER BY e_length ASC');
        $stmt->execute([':start' => $start]);
        $used = [];
        while ($row = $stmt->fetch()) {
            $used[(int) $row['e_length']] = true;
        }
        for ($i = $start; $i <= $max; $i++) {
            if (!isset($used[$i])) {
                return $i;
            }
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
            $upd = $this->pdo->prepare('UPDATE links SET clicks = clicks + 1, updated_at = :now WHERE id = :id');
            $upd->execute([':now' => Helpers::now(), ':id' => $link['id']]);
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
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function stats(): array
    {
        $totalLinks = (int) $this->pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        $totalClicks = (int) $this->pdo->query('SELECT COALESCE(SUM(clicks),0) FROM links')->fetchColumn();
        $today = (int) $this->pdo->query("SELECT COUNT(*) FROM click_logs WHERE clicked_at >= CURDATE()")->fetchColumn();
        return [
            'links' => $totalLinks,
            'clicks' => $totalClicks,
            'today' => $today,
        ];
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
        $sql = "SELECT * FROM links {$where} ORDER BY id DESC LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $per, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per' => $per];
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $stmt = $this->pdo->prepare('UPDATE links SET enabled = :en, updated_at = :now WHERE id = :id');
        $stmt->execute([':en' => $enabled ? 1 : 0, ':now' => Helpers::now(), ':id' => $id]);
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
