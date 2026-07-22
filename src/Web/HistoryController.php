<?php

declare(strict_types=1);

namespace WABridge\Web;

use WABridge\Digest\DigestRepository;

final class HistoryController
{
    public function __construct(private readonly DigestRepository $digests)
    {
    }

    /**
     * @return list<array{id:int,weekLabel:string,digest:array<string,mixed>,createdAt:string}>
     */
    public function listForGroup(int $groupId): array
    {
        return $this->digests->listForGroup($groupId);
    }
}
