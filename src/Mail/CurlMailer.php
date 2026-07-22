<?php

declare(strict_types=1);

namespace WABridge\Mail;

/**
 * Prod sürücüsü: çıplak curl ile transactional email API'sine POST eder
 * (LlmClassifier::call() ile aynı desen — Guzzle/SDK yok).
 *
 * DURUM: API key olmadan koşulamaz; bu depoda CANLI DOĞRULANMADI
 * (LlmClassifier ile aynı dürüstlük ilkesi).
 */
final class CurlMailer implements MailerInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiUrl,
        private readonly string $fromEmail,
    ) {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException(
                'E-posta gönderimi için WABRIDGE_MAIL_API_KEY tanımlı değil. '
                . '.env dosyasını doldurun veya WABRIDGE_MAIL_DRIVER=log kullanın.'
            );
        }
    }

    public function send(string $toEmail, string $subject, string $bodyText): void
    {
        $payload = json_encode([
            'from' => $this->fromEmail,
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $bodyText,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status >= 300) {
            throw new \RuntimeException('E-posta gönderilemedi: ' . ($err !== '' ? $err : 'HTTP ' . $status));
        }
    }
}
