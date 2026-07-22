<?php

declare(strict_types=1);

namespace WABridge\Billing;

/**
 * iyzico webhook imza doğrulama sözleşmesi. Enjekte edilebilir olması,
 * WebhookController'ın DB/idempotency mantığını gerçek imza algoritması
 * olmadan da test edebilmesini sağlar (testler sahte bir doğrulayıcı verir).
 */
interface SignatureVerifierInterface
{
    /**
     * @param array<string,mixed> $payload
     */
    public function verify(array $payload, ?string $signatureHeader): bool;
}
