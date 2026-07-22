<?php

declare(strict_types=1);

namespace WABridge\Web;

use WABridge\Billing\Entitlement;
use WABridge\Digest\DigestRepository;
use WABridge\Pipeline;
use WABridge\Support\Csrf;
use WABridge\Support\Env;

/**
 * Web upload akışı: CSRF + KVKK rızası + ücretsiz/ücretli hak doğrula ->
 * geçici kaydet -> pipeline (ham .txt işlenir ve SİLİNİR) -> digest'i gruba
 * kaydet -> döndür. HTML üretmez (view ayrı).
 */
final class DigestController
{
    public function __construct(
        private readonly ?DigestRepository $digests = null,
        private readonly ?Entitlement $entitlement = null,
    ) {
    }

    /**
     * @param array<string,mixed>                                        $post
     * @param array<string,array{name?:string,tmp_name?:string,error?:int,size?:int}> $files
     * @return array{ok:bool,error:?string,digest:?array<string,mixed>}
     */
    public function handleUpload(array $post, array $files, ?int $groupId = null): array
    {
        if (!Csrf::check(is_string($post['csrf_token'] ?? null) ? $post['csrf_token'] : null)) {
            return $this->fail('Oturum doğrulaması başarısız (CSRF). Sayfayı yenileyip tekrar deneyin.');
        }

        if (empty($post['kvkk_consent'])) {
            return $this->fail('Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir. '
                . 'Ham sohbet işlenir, digest üretilir ve dosya anında silinir.');
        }

        if ($this->entitlement !== null && $groupId !== null && !$this->entitlement->canProcessDigest($groupId)) {
            return $this->fail('Ücretsiz deneme hakkın doldu. Devam etmek için abone ol.');
        }

        $file = $files['chat'] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->fail('Lütfen WhatsApp\'ın "Sohbeti dışa aktar" ile verdiği .txt dosyasını yükleyin.');
        }

        $maxBytes = (int) (Env::get('WABRIDGE_MAX_UPLOAD_BYTES', '10485760'));
        if (($file['size'] ?? 0) > $maxBytes) {
            return $this->fail('Dosya çok büyük. En fazla ' . intdiv($maxBytes, 1048576) . ' MB yükleyebilirsiniz.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return $this->fail('Yükleme doğrulanamadı. Lütfen tekrar deneyin.');
        }

        $name = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
            return $this->fail('Yalnızca .txt uzantılı export dosyaları kabul edilir.');
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($storageDir) && !@mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
            return $this->fail('Sunucu geçici depolamayı hazırlayamadı. Lütfen sonra tekrar deneyin.');
        }

        $dest = $storageDir . '/' . bin2hex(random_bytes(8)) . '.txt';
        if (!move_uploaded_file($tmpName, $dest)) {
            return $this->fail('Dosya sunucuya alınamadı. Lütfen tekrar deneyin.');
        }

        try {
            // processFile ham .txt'yi işler ve SİLER (KVKK).
            $digest = Pipeline::fromEnv()->processFile($dest);
        } catch (\Throwable $e) {
            // Güvenlik: iç hata detayını kullanıcıya sızdırma.
            if (is_file($dest)) {
                @unlink($dest);
            }
            return $this->fail('Sohbet işlenemedi. Dosyanın resmî WhatsApp export .txt olduğundan emin olun.');
        }

        if ($this->digests !== null && $groupId !== null) {
            $sourceMessageCount = count($digest['takvim']) + count($digest['senden_aksiyon'])
                + count($digest['para_talepleri']) + $digest['elenen_gurultu_sayisi'];
            $this->digests->save($groupId, $digest, $sourceMessageCount);
        }

        return ['ok' => true, 'error' => null, 'digest' => $digest];
    }

    /**
     * @return array{ok:false,error:string,digest:null}
     */
    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'digest' => null];
    }
}
