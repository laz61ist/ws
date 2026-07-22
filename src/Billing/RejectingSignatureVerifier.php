<?php

declare(strict_types=1);

namespace WABridge\Billing;

/**
 * Varsayılan (prod) doğrulayıcı: HER ZAMAN reddeder.
 *
 * DÜRÜSTLÜK NOTU: iyzico'nun webhook imza şeması (IYZWSv2, HMAC-SHA256) bu
 * oturumda docs.iyzico.com'a erişim engellendiği için birebir doğrulanamadı.
 * Yanlış bir algoritmayı "doğru" gibi sunup sahte webhook'ları kabul etmek,
 * hiç kabul etmemekten daha kötü bir güvenlik açığıdır — bu yüzden bilinçli
 * olarak FAIL-CLOSED: gerçek algoritma docs/resmi SDK'dan doğrulanıp bu
 * sınıfın yerine geçecek gerçek bir implementasyon yazılana kadar HİÇBİR
 * webhook kabul edilmez. Canlıya geçmeden önce MUTLAKA değiştirilmeli.
 */
final class RejectingSignatureVerifier implements SignatureVerifierInterface
{
    public function verify(array $payload, ?string $signatureHeader): bool
    {
        return false;
    }
}
