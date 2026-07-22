<?php

declare(strict_types=1);

namespace WABridge\Billing;

use WABridge\Storage\Database;

/**
 * iyzico webhook alıcısı. İdempotency mantığı (payment_events.iyzico_event_id
 * UNIQUE ile çift-işlem engeli) TAM GERÇEK ve testlerle doğrulanmıştır — bu
 * kısım iyzico'ya bağımlı değil, herhangi bir JSON payload ile test edilebilir.
 *
 * DÜRÜSTLÜK NOTU: imza doğrulaması enjekte edilebilir (SignatureVerifierInterface).
 * Prod varsayılanı RejectingSignatureVerifier — HER ZAMAN reddeder, çünkü
 * iyzico'nun gerçek imza şeması bu oturumda doğrulanamadı (bkz. o sınıfın
 * docblock'u). Testler DB/idempotency mantığını izole test etmek için sahte
 * bir doğrulayıcı enjekte eder.
 */
final class WebhookController
{
    public function __construct(
        private readonly Database $db,
        private readonly SignatureVerifierInterface $verifier = new RejectingSignatureVerifier(),
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,error:?string}
     */
    public function handle(array $payload, ?string $signatureHeader): array
    {
        if (!$this->verifier->verify($payload, $signatureHeader)) {
            return ['ok' => false, 'error' => 'İmza doğrulanamadı, event reddedildi.'];
        }

        $eventId = is_string($payload['eventId'] ?? null) ? $payload['eventId'] : null;
        $type = is_string($payload['eventType'] ?? null) ? $payload['eventType'] : 'unknown';
        if ($eventId === null || $eventId === '') {
            return ['ok' => false, 'error' => 'Geçersiz payload: eventId eksik.'];
        }

        try {
            $this->db->execute(
                'INSERT INTO payment_events (iyzico_event_id, type, raw_payload_json) VALUES (:eid, :type, :raw)',
                [
                    'eid' => $eventId,
                    'type' => $type,
                    'raw' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ],
            );
        } catch (\PDOException $e) {
            // UNIQUE ihlali = bu event daha önce işlendi. Idempotent: hata değil, no-op.
            return ['ok' => true, 'error' => null];
        }

        $this->applyStatusChange($payload);

        $this->db->execute(
            "UPDATE payment_events SET processed_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE iyzico_event_id = :eid",
            ['eid' => $eventId],
        );

        return ['ok' => true, 'error' => null];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function applyStatusChange(array $payload): void
    {
        $subscriptionRef = is_string($payload['subscriptionReferenceCode'] ?? null)
            ? $payload['subscriptionReferenceCode']
            : null;
        $status = is_string($payload['status'] ?? null) ? $payload['status'] : null;
        if ($subscriptionRef === null || $status === null) {
            return;
        }

        $this->db->execute(
            "UPDATE subscriptions SET status = :status, updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now')
             WHERE iyzico_ref = :ref",
            ['status' => $status, 'ref' => $subscriptionRef],
        );
    }
}
