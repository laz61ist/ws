<?php

declare(strict_types=1);

namespace WABridge\Billing;

/**
 * iyzico REST API istemcisi (sandbox: https://sandbox-api.iyzipay.com — resmi
 * iyzico GitHub deposundan doğrulandı).
 *
 * DURUM — DÜRÜSTLÜK NOTU (LlmClassifier ile aynı ilke):
 * iyzico'nun imza şeması (IYZWSv2: HMAC-SHA256 tabanlı, randomKey+uriPath+body
 * birleşimi) bu oturumda resmi docs.iyzico.com'a erişim engellendiği için
 * BİREBİR doğrulanamadı. Ödeme API'sinde YANLIŞ bir imza algoritmasını "doğru"
 * gibi sunmak, sessizce başarısız olan ya da güvenlik açığı taşıyan bir
 * entegrasyona yol açabilir — bu yüzden burada TAHMİNİ bir imza üretmek yerine
 * bilinçli olarak signRequest() metodunu "doğrulanmadı" istisnasıyla bırakıyoruz.
 *
 * Canlıya geçmeden önce: (a) gerçek sandbox hesabı aç (sadece e-posta gerekir,
 * https://sandbox-merchant.iyzipay.com/auth/register — vergi levhası GEREKMEZ,
 * bu doğrulandı), (b) docs.iyzico.com'daki güncel imza algoritmasını BİREBİR
 * uygula veya resmi `iyzico/iyzipay-php` composer paketini bu modüle özel
 * bir bağımlılık istisnası olarak ekle, (c) signRequest()'i gerçek sandbox
 * key'iyle test et.
 */
final class IyzicoClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function post(string $path, array $body): array
    {
        if (trim($this->apiKey) === '' || trim($this->secretKey) === '') {
            throw new \RuntimeException(
                'iyzico entegrasyonu için WABRIDGE_IYZICO_API_KEY / WABRIDGE_IYZICO_SECRET_KEY tanımlı değil. '
                . 'Sandbox hesabı aç (docs.iyzico.com), .env doldur.'
            );
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $authHeader = $this->signRequest($path, (string) $payload);

        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'authorization: ' . $authHeader,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('iyzico çağrısı başarısız: ' . $err);
        }
        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * DOĞRULANMADI: iyzico'nun IYZWSv2 imza şeması resmi dokümandan birebir
     * teyit edilemedi. Gerçek entegrasyon öncesi bu metot resmi SDK/docs'a
     * göre yeniden yazılmalı — şu an bilinçli olarak fırlatıyor.
     */
    private function signRequest(string $path, string $body): string
    {
        throw new \RuntimeException(
            'IyzicoClient::signRequest() henüz DOĞRULANMAMIŞ bir imza şeması kullanacaktı, '
            . 'bu yüzden bilinçli olarak devre dışı bırakıldı. Gerçek sandbox entegrasyonu için '
            . 'docs.iyzico.com/urunler/abonelik veya resmi iyzico/iyzipay-php paketinden '
            . 'imza algoritmasını birebir uygulayın (bkz. docs/PRODUCT_v1_DESIGN.md).'
        );
    }
}
