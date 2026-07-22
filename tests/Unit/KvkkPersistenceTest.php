<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Classify\HeuristicClassifier;
use WABridge\Digest\DigestRepository;
use WABridge\Pipeline;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

/**
 * KVKK — kalıcılık katmanı genişlemesi: digest ARTIK saklanıyor (v1a), ama
 * bu, ham export içeriğinin sızmasına izin vermemeli. Bu test DigestBuilder'ın
 * ürettiği JSON'un round-trip birebir aynı kaldığını VE ham export'taki
 * gönderen adlarının/gürültü mesajlarının depoya hiç ulaşmadığını doğrular.
 */
final class KvkkPersistenceTest extends TestCase
{
    private const SENDER_NAMES = ['Ayşe K.', 'Mehmet T.', 'Fatma Y.', 'Ali D.', 'Selin A.', 'Zeynep Öğretmen'];
    private const NOISE_SNIPPETS = [
        'markette süt indirimdeymiş',
        'komşu sınıfın öğretmeni değişmiş',
        'hiç param kalmadı bu ay',
    ];

    private function db(): Database
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        return $db;
    }

    public function testStoredPayloadRoundTripsExactly(): void
    {
        $db = $this->db();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();

        $raw = (string) file_get_contents(dirname(__DIR__) . '/fixtures/synthetic_android_tr.txt');
        $digest = (new Pipeline(new HeuristicClassifier()))->processString($raw);

        $repo = new DigestRepository($db);
        $repo->save($groupId, $digest, 20);

        $stored = $repo->listForGroup($groupId)[0]['digest'];
        $this->assertSame(
            json_encode($digest, JSON_UNESCAPED_UNICODE),
            json_encode($stored, JSON_UNESCAPED_UNICODE),
            'Kaydedilen digest, DigestBuilder çıktısıyla birebir aynı olmalı',
        );
    }

    public function testSenderNamesNeverPersisted(): void
    {
        $db = $this->db();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();

        $raw = (string) file_get_contents(dirname(__DIR__) . '/fixtures/synthetic_android_tr.txt');
        $digest = (new Pipeline(new HeuristicClassifier()))->processString($raw);
        (new DigestRepository($db))->save($groupId, $digest, 20);

        $row = $db->fetchOne('SELECT payload_json FROM digests WHERE group_id = :g', ['g' => $groupId]);
        $payload = (string) $row['payload_json'];

        foreach (self::SENDER_NAMES as $name) {
            $this->assertFalse(
                str_contains($payload, $name),
                "Gönderen adı '{$name}' kalıcı depoda görünmemeli",
            );
        }
    }

    public function testNoiseMessageContentNeverPersisted(): void
    {
        $db = $this->db();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();

        $raw = (string) file_get_contents(dirname(__DIR__) . '/fixtures/synthetic_android_tr.txt');
        $digest = (new Pipeline(new HeuristicClassifier()))->processString($raw);
        (new DigestRepository($db))->save($groupId, $digest, 20);

        $row = $db->fetchOne('SELECT payload_json FROM digests WHERE group_id = :g', ['g' => $groupId]);
        $payload = (string) $row['payload_json'];

        foreach (self::NOISE_SNIPPETS as $snippet) {
            $this->assertFalse(
                str_contains($payload, $snippet),
                "Gürültü mesajı parçası '{$snippet}' kalıcı depoda görünmemeli",
            );
        }
    }

    public function testDeletingGroupRemovesStoredDigests(): void
    {
        $db = $this->db();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();

        $repo = new DigestRepository($db);
        $repo->save($groupId, ['hafta' => '2026-01-19/25', 'takvim' => [], 'senden_aksiyon' => [], 'para_talepleri' => [], 'elenen_gurultu_sayisi' => 0], 1);
        $this->assertSame(1, $repo->countForGroup($groupId));

        $repo->deleteForGroup($groupId);
        $this->assertSame(0, $repo->countForGroup($groupId), 'Grup silme işleminden sonra digest kaydı kalmamalı');
    }
}
