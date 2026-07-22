<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Billing\RejectingSignatureVerifier;
use WABridge\Billing\SignatureVerifierInterface;
use WABridge\Billing\WebhookController;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

/**
 * WebhookController testleri gerçek network çağrısı YAPMAZ — sahte iyzico
 * payload'ı doğrudan handle()'a verilir. İmza doğrulaması iki ayrı senaryoda
 * test edilir: (1) prod varsayılanı (RejectingSignatureVerifier — HER ZAMAN
 * reddeder, gerçek iyzico webhook'u bile bu haliyle geçmez — bilinçli), (2)
 * DB/idempotency mantığı, sahte bir "her zaman kabul et" doğrulayıcıyla izole.
 */
final class WebhookTest extends TestCase
{
    private function db(): Database
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        return $db;
    }

    private function acceptingVerifier(): SignatureVerifierInterface
    {
        return new class implements SignatureVerifierInterface {
            public function verify(array $payload, ?string $signatureHeader): bool
            {
                return true;
            }
        };
    }

    public function testDefaultVerifierRejectsEverySignature(): void
    {
        // Prod varsayılanı: iyzico'nun gerçek imza şeması doğrulanamadığı için
        // FAIL-CLOSED. Bu test o kararın kodda gerçekten uygulandığını kanıtlar.
        $db = $this->db();
        $controller = new WebhookController($db, new RejectingSignatureVerifier());

        $result = $controller->handle(['eventId' => 'evt_1', 'eventType' => 'subscription.activated'], 'her-turlu-imza');

        $this->assertFalse($result['ok'], 'Varsayılan doğrulayıcı HER ZAMAN reddetmeli (fail-closed)');
        $count = $db->fetchOne('SELECT COUNT(*) AS n FROM payment_events');
        $this->assertSame(0, (int) $count['n'], 'Reddedilen event payment_events\'e YAZILMAMALI');
    }

    public function testValidEventPersistedIdempotently(): void
    {
        $db = $this->db();
        $controller = new WebhookController($db, $this->acceptingVerifier());

        $payload = ['eventId' => 'evt_abc123', 'eventType' => 'subscription.activated'];
        $result = $controller->handle($payload, 'ignored-in-test');

        $this->assertTrue($result['ok']);
        $count = $db->fetchOne('SELECT COUNT(*) AS n FROM payment_events WHERE iyzico_event_id = :id', ['id' => 'evt_abc123']);
        $this->assertSame(1, (int) $count['n']);
    }

    public function testDuplicateEventIdProcessedOnlyOnce(): void
    {
        $db = $this->db();
        $controller = new WebhookController($db, $this->acceptingVerifier());

        $payload = ['eventId' => 'evt_dup', 'eventType' => 'subscription.activated'];
        $first = $controller->handle($payload, 'x');
        $second = $controller->handle($payload, 'x');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok'], 'Tekrar gelen aynı event hata değil, idempotent no-op olmalı');

        $count = $db->fetchOne('SELECT COUNT(*) AS n FROM payment_events WHERE iyzico_event_id = :id', ['id' => 'evt_dup']);
        $this->assertSame(1, (int) $count['n'], 'Aynı event_id İKİ KEZ satır olarak YAZILMAMALI');
    }

    public function testSubscriptionStatusUpdatedFromEvent(): void
    {
        $db = $this->db();
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();
        $db->execute(
            "INSERT INTO subscriptions (group_id, iyzico_ref, plan_code, status) VALUES (:g, 'sub_ref_1', 'monthly', 'pending')",
            ['g' => $groupId],
        );

        $controller = new WebhookController($db, $this->acceptingVerifier());
        $controller->handle([
            'eventId' => 'evt_status_1',
            'eventType' => 'subscription.activated',
            'subscriptionReferenceCode' => 'sub_ref_1',
            'status' => 'active',
        ], 'x');

        $row = $db->fetchOne('SELECT status FROM subscriptions WHERE iyzico_ref = :ref', ['ref' => 'sub_ref_1']);
        $this->assertSame('active', $row['status']);
    }

    public function testMissingEventIdRejected(): void
    {
        $db = $this->db();
        $controller = new WebhookController($db, $this->acceptingVerifier());

        $result = $controller->handle(['eventType' => 'subscription.activated'], 'x');
        $this->assertFalse($result['ok'], 'eventId eksikse reddedilmeli');
    }
}
