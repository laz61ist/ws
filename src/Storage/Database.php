<?php

declare(strict_types=1);

namespace WABridge\Storage;

/**
 * PDO/SQLite ince sarmalayıcı. Zero-dependency felsefesiyle uyumlu:
 * ext-pdo_sqlite bir PHP çekirdek eklentisi, composer paketi değil.
 */
final class Database
{
    private \PDO $pdo;

    /**
     * @param string $dsn ör. "sqlite:/path/to/wabridge.sqlite" veya "sqlite::memory:"
     */
    public function __construct(string $dsn)
    {
        $this->pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
    }

    public static function fromFile(string $path): self
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Veritabanı dizini oluşturulamadı: ' . $dir);
        }
        return new self('sqlite:' . $path);
    }

    public static function inMemory(): self
    {
        return new self('sqlite::memory:');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<int|string,mixed> $params
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->execute($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
