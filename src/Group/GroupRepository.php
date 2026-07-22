<?php

declare(strict_types=1);

namespace WABridge\Group;

use WABridge\Storage\Database;

final class GroupRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Kullanıcının ilk grubunu döner; hiç yoksa "Grubum" adıyla otomatik
     * oluşturur (onboarding sürtünmesini azaltmak için — grup adı sorma
     * adımı v1a'da yok).
     */
    public function firstOrCreateDefault(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM groups WHERE owner_user_id = :uid ORDER BY id ASC LIMIT 1',
            ['uid' => $userId],
        );
        if ($row !== null) {
            return (int) $row['id'];
        }

        $this->db->execute(
            'INSERT INTO groups (owner_user_id, name) VALUES (:uid, :name)',
            ['uid' => $userId, 'name' => 'Grubum'],
        );
        return $this->db->lastInsertId();
    }

    public function find(int $groupId): ?Group
    {
        $row = $this->db->fetchOne('SELECT * FROM groups WHERE id = :id', ['id' => $groupId]);
        if ($row === null) {
            return null;
        }
        return new Group((int) $row['id'], (int) $row['owner_user_id'], (string) $row['name']);
    }

    /** Grubun sahibi bu kullanıcı mı — yetki kontrolü için. */
    public function belongsToUser(int $groupId, int $userId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM groups WHERE id = :gid AND owner_user_id = :uid',
            ['gid' => $groupId, 'uid' => $userId],
        );
        return $row !== null;
    }
}
