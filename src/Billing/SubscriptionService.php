<?php

declare(strict_types=1);

namespace WABridge\Billing;

use WABridge\Storage\Database;

/**
 * Abonelik yaşam döngüsü: checkout linki oluşturma, durum sorgulama, iptal.
 *
 * DURUM: IyzicoClient::post() gerçek imza şeması doğrulanmadan çağrı
 * YAPAMAYACAĞI için (bkz. IyzicoClient docblock), bu sınıfın metotları da
 * canlı kullanılamaz — DB tarafı (subscriptions tablosu okuma/yazma) gerçek
 * ve test edilebilir, iyzico'ya giden kısım DEĞİL.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly Database $db,
        private readonly IyzicoClient $client,
    ) {
    }

    /**
     * Grup için pending bir abonelik kaydı açar ve iyzico checkout formu
     * başlatma çağrısını yapar. DURUM: IyzicoClient::post() DOĞRULANMAMIŞ
     * imza şeması yüzünden istisna fırlatır — bu metot canlı ÇAĞRILAMAZ.
     *
     * @return array{checkoutFormContent:string,token:string}
     */
    public function startCheckout(int $groupId, string $planCode): array
    {
        $this->db->execute(
            "INSERT INTO subscriptions (group_id, plan_code, status) VALUES (:g, :plan, 'pending')",
            ['g' => $groupId, 'plan' => $planCode],
        );

        // DOĞRULANMADI: gerçek iyzico checkout-form-initialize endpoint şeması
        // ve imzası doğrulanmadan bu çağrı yapılmamalı (IyzicoClient fırlatır).
        $response = $this->client->post('/payment/iyzipos/checkoutform/initialize/auth/ecom', [
            'conversationId' => (string) $groupId,
            'basketId' => 'wabridge-' . $planCode,
        ]);

        return [
            'checkoutFormContent' => (string) ($response['checkoutFormContent'] ?? ''),
            'token' => (string) ($response['token'] ?? ''),
        ];
    }

    public function status(int $groupId): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT status FROM subscriptions WHERE group_id = :g ORDER BY id DESC LIMIT 1',
            ['g' => $groupId],
        );
        return $row !== null ? (string) $row['status'] : null;
    }

    /** DB tarafını iptal olarak işaretler; iyzico'ya gerçek iptal çağrısı ayrıca gerekir (DOĞRULANMADI). */
    public function markCanceled(int $groupId): void
    {
        $this->db->execute(
            "UPDATE subscriptions SET status = 'canceled', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now')
             WHERE group_id = :g",
            ['g' => $groupId],
        );
    }
}
