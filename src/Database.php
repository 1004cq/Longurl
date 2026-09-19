<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $db = $config['db'] ?? [];
        $dsn = (string) ($db['dsn'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $pass = (string) ($db['pass'] ?? '');
        if ($dsn === '') {
            throw new RuntimeException('Database is not configured.');
        }
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }

    /** Short insert sessions avoid RR next-key locks on nearby e_length gaps. */
    public static function useReadCommitted(PDO $pdo): void
    {
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
}
