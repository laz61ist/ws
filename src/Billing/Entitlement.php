<?php

declare(strict_types=1);

namespace WABridge\Billing;

use WABridge\Digest\DigestRepository;
use WABridge\Storage\Database;

/**
 * Ücretsiz/ücretli sınır: grup başına ilk WABRIDGE_FREE_DIGEST_LIMIT digest
 * ücretsiz, sonrası aktif abonelik gerektirir. iyzico'ya bağımlı DEĞİL —
 * saf DB sorgusu, iyzico key'i olmadan da tam test edilebilir.
 */
final class Entitlement
{
    public function __construct(
        private readonly Database $db,
        private readonly DigestRepository $digests,
        private readonly int $freeLimit = 4,
    ) {
    }

    public function canProcessDigest(int $groupId): bool
    {
        if ($this->hasActiveSubscription($groupId)) {
            return true;
        }
        return $this->digests->countForGroup($groupId) < $this->freeLimit;
    }

    public function hasActiveSubscription(int $groupId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM subscriptions WHERE group_id = :gid AND status = 'active'",
            ['gid' => $groupId],
        );
        return $row !== null;
    }

    public function remainingFreeDigests(int $groupId): int
    {
        if ($this->hasActiveSubscription($groupId)) {
            return PHP_INT_MAX;
        }
        return max(0, $this->freeLimit - $this->digests->countForGroup($groupId));
    }
}
