<?php

declare(strict_types=1);

namespace WABridge\Digest;

use WABridge\Storage\Database;

/**
 * DigestBuilder'ın ürettiği array'i kalıcı depoya yazar/okur.
 * DigestBuilder'ın KENDİSİNE dokunmaz — bu sınıf onun çıktısını TÜKETİR.
 *
 * KVKK: yalnızca ÜRETİLMİŞ digest JSON'u saklanır. Ham export/mesaj metni
 * bu katmandan asla geçmez (Pipeline zaten ham dosyayı işlem sonrası siler).
 */
final class DigestRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{hafta:string,takvim:list<array<string,mixed>>,senden_aksiyon:list<string>,para_talepleri:list<array<string,mixed>>,elenen_gurultu_sayisi:int} $digest
     */
    public function save(int $groupId, array $digest, int $sourceMessageCount): int
    {
        $this->db->execute(
            'INSERT INTO digests (group_id, week_label, payload_json, noise_count, source_message_count)
             VALUES (:gid, :week, :payload, :noise, :count)',
            [
                'gid' => $groupId,
                'week' => $digest['hafta'],
                'payload' => json_encode($digest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'noise' => $digest['elenen_gurultu_sayisi'],
                'count' => $sourceMessageCount,
            ],
        );
        return $this->db->lastInsertId();
    }

    public function countForGroup(int $groupId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM digests WHERE group_id = :gid',
            ['gid' => $groupId],
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<array{id:int,weekLabel:string,digest:array<string,mixed>,createdAt:string}>
     */
    public function listForGroup(int $groupId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, week_label, payload_json, created_at FROM digests
             WHERE group_id = :gid ORDER BY id DESC',
            ['gid' => $groupId],
        );

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'weekLabel' => (string) $r['week_label'],
                'digest' => json_decode((string) $r['payload_json'], true, 512, JSON_THROW_ON_ERROR),
                'createdAt' => (string) $r['created_at'],
            ],
            $rows,
        );
    }

    /** Hesap/unutulma hakkı: bir gruba ait tüm digest'leri siler. */
    public function deleteForGroup(int $groupId): void
    {
        $this->db->execute('DELETE FROM digests WHERE group_id = :gid', ['gid' => $groupId]);
    }
}
