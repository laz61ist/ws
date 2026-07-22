<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Billing\Entitlement;
use WABridge\Digest\DigestRepository;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

final class EntitlementTest extends TestCase
{
    /** @return array{0:Database,1:DigestRepository,2:int} */
    private function setupGroup(): array
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        $db->execute('INSERT INTO users (email) VALUES (:e)', ['e' => 'veli@ornek.com']);
        $userId = $db->lastInsertId();
        $db->execute('INSERT INTO groups (owner_user_id, name) VALUES (:u, :n)', ['u' => $userId, 'n' => 'Grubum']);
        $groupId = $db->lastInsertId();
        return [$db, new DigestRepository($db), $groupId];
    }

    private function fakeDigest(): array
    {
        return ['hafta' => '2026-01-19/25', 'takvim' => [], 'senden_aksiyon' => [], 'para_talepleri' => [], 'elenen_gurultu_sayisi' => 0];
    }

    public function testFirstFourDigestsAllowedWithoutSubscription(): void
    {
        [$db, $digests, $groupId] = $this->setupGroup();
        $entitlement = new Entitlement($db, $digests, 4);

        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue($entitlement->canProcessDigest($groupId), "Digest #{$i} ücretsiz limitte izinli olmalı");
            $digests->save($groupId, $this->fakeDigest(), 1);
        }
    }

    public function testFifthDigestBlockedWithoutSubscription(): void
    {
        [$db, $digests, $groupId] = $this->setupGroup();
        $entitlement = new Entitlement($db, $digests, 4);

        for ($i = 0; $i < 4; $i++) {
            $digests->save($groupId, $this->fakeDigest(), 1);
        }

        $this->assertFalse($entitlement->canProcessDigest($groupId), '5. digest ücretsiz limit doluyken reddedilmeli');
        $this->assertSame(0, $entitlement->remainingFreeDigests($groupId));
    }

    public function testActiveSubscriptionBypassesLimit(): void
    {
        [$db, $digests, $groupId] = $this->setupGroup();
        $entitlement = new Entitlement($db, $digests, 4);

        for ($i = 0; $i < 6; $i++) {
            $digests->save($groupId, $this->fakeDigest(), 1);
        }
        $this->assertFalse($entitlement->canProcessDigest($groupId), 'Abonelik yokken 6. digest reddedilmeli');

        $db->execute(
            "INSERT INTO subscriptions (group_id, plan_code, status) VALUES (:g, 'monthly', 'active')",
            ['g' => $groupId],
        );

        $this->assertTrue($entitlement->canProcessDigest($groupId), 'Aktif abonelik varsa limit uygulanmamalı');
        $this->assertSame(PHP_INT_MAX, $entitlement->remainingFreeDigests($groupId));
    }

    public function testCanceledSubscriptionDoesNotBypassLimit(): void
    {
        [$db, $digests, $groupId] = $this->setupGroup();
        $entitlement = new Entitlement($db, $digests, 4);

        $db->execute(
            "INSERT INTO subscriptions (group_id, plan_code, status) VALUES (:g, 'monthly', 'canceled')",
            ['g' => $groupId],
        );
        for ($i = 0; $i < 4; $i++) {
            $digests->save($groupId, $this->fakeDigest(), 1);
        }

        $this->assertFalse($entitlement->canProcessDigest($groupId), "status='canceled' aktif abonelik SAYILMAMALI");
    }
}
