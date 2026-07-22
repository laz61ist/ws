<?php

declare(strict_types=1);

namespace WABridge\Auth;

use WABridge\Group\GroupRepository;
use WABridge\Mail\MailerInterface;
use WABridge\Storage\Database;

/**
 * Parolasız kimlik doğrulama: e-posta → tek-kullanımlık, hash'lenmiş,
 * süreli link. Plaintext token ASLA DB'ye yazılmaz — yalnızca link/e-postada
 * geçer, DB'de sha256 hash'i tutulur (KVKK + genel güvenlik iyi pratiği).
 */
final class MagicLinkService
{
    public function __construct(
        private readonly Database $db,
        private readonly MailerInterface $mailer,
        private readonly GroupRepository $groups,
        private readonly string $appBaseUrl,
        private readonly int $ttlMinutes = 20,
    ) {
    }

    /**
     * E-postaya link gönderir. Kullanıcı yoksa oluşturur (upsert-by-email).
     */
    public function requestLink(string $email): void
    {
        $email = trim(mb_strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Geçerli bir e-posta adresi girin.');
        }

        $userId = $this->findOrCreateUser($email);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+' . $this->ttlMinutes . ' minutes'))->format('Y-m-d\TH:i:s\Z');

        $this->db->execute(
            'INSERT INTO magic_links (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)',
            ['uid' => $userId, 'hash' => $tokenHash, 'exp' => $expiresAt],
        );

        $link = rtrim($this->appBaseUrl, '/') . '/magic.php?token=' . urlencode($token);
        $this->mailer->send(
            $email,
            'WABridge giriş bağlantın',
            "Merhaba,\n\nWABridge'e giriş yapmak için bu bağlantıya tıkla (20 dakika geçerli):\n{$link}\n\nBu isteği sen yapmadıysan bu e-postayı yok sayabilirsin.",
        );
    }

    /**
     * Token'ı doğrular, tüketir (tek kullanımlık işaretler), kullanıcının ilk
     * grubunu garanti eder. Başarısızsa null döner.
     *
     * @return array{userId:int,groupId:int}|null
     */
    public function consume(string $token): ?array
    {
        if (trim($token) === '') {
            return null;
        }
        $tokenHash = hash('sha256', $token);

        $row = $this->db->fetchOne(
            'SELECT id, user_id, expires_at, used_at FROM magic_links WHERE token_hash = :hash',
            ['hash' => $tokenHash],
        );
        if ($row === null) {
            return null;
        }
        if ($row['used_at'] !== null) {
            return null;
        }
        $expiresAt = new \DateTimeImmutable($row['expires_at']);
        if ($expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        $this->db->execute(
            "UPDATE magic_links SET used_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = :id",
            ['id' => $row['id']],
        );

        $userId = (int) $row['user_id'];
        $groupId = $this->groups->firstOrCreateDefault($userId);

        return ['userId' => $userId, 'groupId' => $groupId];
    }

    private function findOrCreateUser(string $email): int
    {
        $existing = $this->db->fetchOne('SELECT id FROM users WHERE email = :email', ['email' => $email]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->db->execute('INSERT INTO users (email) VALUES (:email)', ['email' => $email]);
        return $this->db->lastInsertId();
    }
}
