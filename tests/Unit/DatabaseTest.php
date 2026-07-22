<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

final class DatabaseTest extends TestCase
{
    private function migratedDb(): Database
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        return $db;
    }

    public function testMigrationCreatesAllExpectedTables(): void
    {
        $db = $this->migratedDb();
        $tables = array_column(
            $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"),
            'name',
        );
        foreach (['users', 'magic_links', 'groups', 'digests', 'subscriptions', 'payment_events'] as $expected) {
            $this->assertTrue(in_array($expected, $tables, true), "Tablo eksik: {$expected}");
        }
    }

    public function testMigrationIsIdempotent(): void
    {
        $db = $this->migratedDb();
        // İkinci çalıştırma hata fırlatmamalı (CREATE TABLE IF NOT EXISTS).
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        $this->assertTrue(true, 'İkinci migrate() çağrısı istisna fırlatmadı');
    }

    public function testForeignKeysEnforced(): void
    {
        $db = $this->migratedDb();
        $threw = false;
        try {
            // groups.owner_user_id var olmayan bir users.id'ye işaret ediyor -> FK ihlali.
            $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (999, :name)', ['name' => 'x']);
        } catch (\PDOException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Foreign key kısıtlaması aktif olmalı (PRAGMA foreign_keys=ON)');
    }

    public function testCascadeDeleteRemovesDependentRows(): void
    {
        $db = $this->migratedDb();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'test@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();
        $db->execute(
            'INSERT INTO digests (group_id, week_label, payload_json, noise_count, source_message_count) VALUES (:g, :w, :p, 0, 1)',
            ['g' => $groupId, 'w' => '2026-01-19/25', 'p' => '{}'],
        );

        $db->execute('DELETE FROM users WHERE id = :id', ['id' => $userId]);

        $remaining = $db->fetchAll('SELECT * FROM digests WHERE group_id = :g', ['g' => $groupId]);
        $this->assertCount(0, $remaining, 'Kullanıcı silinince cascade ile digest de silinmeli');
    }
}
