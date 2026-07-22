<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Billing\IyzicoClient;
use WABridge\Billing\SubscriptionService;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

/**
 * SubscriptionService'in DB tarafı (iyzico'ya bağımlı olmayan kısım) test
 * edilir. startCheckout() IyzicoClient'a bağımlı olduğu ve gerçek key olmadan
 * DOĞRULANMADI istisnası fırlattığı için burada TEST EDİLMEZ (bilinçli).
 */
final class SubscriptionServiceTest extends TestCase
{
    /** @return array{0:Database,1:SubscriptionService,2:int} */
    private function setup(): array
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();
        $client = new IyzicoClient('unused', 'unused', 'https://sandbox-api.iyzipay.com');
        return [$db, new SubscriptionService($db, $client), $groupId];
    }

    public function testStatusNullWhenNoSubscription(): void
    {
        [, $service, $groupId] = $this->setup();
        $this->assertTrue($service->status($groupId) === null);
    }

    public function testMarkCanceledUpdatesStatus(): void
    {
        [$db, $service, $groupId] = $this->setup();
        $db->execute(
            "INSERT INTO subscriptions (group_id, plan_code, status) VALUES (:g, 'monthly', 'active')",
            ['g' => $groupId],
        );

        $service->markCanceled($groupId);

        $this->assertSame('canceled', $service->status($groupId));
    }

    public function testIyzicoClientThrowsWithoutRealCredentials(): void
    {
        // Dürüstlük kontrolü: gerçek key olmadan startCheckout() SESSİZCE
        // "başarılı" DÖNMEMELİ — bilinçli olarak istisna fırlatmalı.
        [, $service, $groupId] = $this->setup();
        $threw = false;
        try {
            $service->startCheckout($groupId, 'monthly');
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Doğrulanmamış iyzico entegrasyonu sessizce başarılı dönmemeli');
    }
}
