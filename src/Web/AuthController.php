<?php

declare(strict_types=1);

namespace WABridge\Web;

use WABridge\Auth\AuthSession;
use WABridge\Auth\MagicLinkService;
use WABridge\Storage\Database;
use WABridge\Support\Csrf;

/**
 * Parolasız giriş akışı: e-posta iste -> magic link -> tüket -> session aç.
 * Mevcut DigestController ile aynı desen: HTML üretmez, sonuç array'i döner.
 */
final class AuthController
{
    public function __construct(
        private readonly MagicLinkService $magicLinks,
        private readonly Database $db,
    ) {
    }

    /**
     * @param array<string,mixed> $post
     * @return array{ok:bool,error:?string}
     */
    public function requestLink(array $post): array
    {
        if (!Csrf::check(is_string($post['csrf_token'] ?? null) ? $post['csrf_token'] : null)) {
            return ['ok' => false, 'error' => 'Oturum doğrulaması başarısız (CSRF). Sayfayı yenileyip tekrar deneyin.'];
        }

        $email = is_string($post['email'] ?? null) ? trim($post['email']) : '';
        if ($email === '') {
            return ['ok' => false, 'error' => 'Lütfen e-posta adresini girin.'];
        }

        try {
            $this->magicLinks->requestLink($email);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            // Kullanıcıya iç hata detayı sızdırılmaz (güvenlik) ama sunucu
            // log'una (Render "Logs" sekmesinde görünür) yazılır — aksi halde
            // gerçek sebep (yanlış API key, sağlayıcı reddi vb.) hiçbir yerde
            // görünmez, teşhis imkansızlaşır.
            error_log('[WABridge] Magic-link gönderilemedi: ' . $e::class . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Bağlantı gönderilemedi. Lütfen daha sonra tekrar deneyin.'];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public function consumeToken(string $token): array
    {
        $result = $this->magicLinks->consume($token);
        if ($result === null) {
            return ['ok' => false, 'error' => 'Bağlantının süresi dolmuş veya daha önce kullanılmış. Yeniden giriş bağlantısı iste.'];
        }

        AuthSession::login($result['userId'], $result['groupId']);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Hesabı ve ilişkili tüm verileri (gruplar, digest'ler, magic link'ler)
     * siler — KVKK unutulma hakkı. Cascade FK'ler (ON DELETE CASCADE) alt
     * kayıtları otomatik temizler.
     */
    public function deleteAccount(int $userId): void
    {
        $this->db->execute('DELETE FROM users WHERE id = :id', ['id' => $userId]);
        AuthSession::logout();
    }
}
