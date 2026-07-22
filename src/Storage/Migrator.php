<?php

declare(strict_types=1);

namespace WABridge\Storage;

/**
 * storage/migrations/*.sql dosyalarını sırayla, idempotent uygular
 * (dosyalar CREATE TABLE IF NOT EXISTS kullanır — iki kez çalıştırmak güvenli).
 */
final class Migrator
{
    public function __construct(private readonly Database $db)
    {
    }

    public function migrate(string $migrationsDir): void
    {
        $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);
            if (trim($sql) === '') {
                continue;
            }
            $this->db->pdo()->exec($sql);
        }
    }
}
