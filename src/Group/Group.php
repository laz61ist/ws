<?php

declare(strict_types=1);

namespace WABridge\Group;

final readonly class Group
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public string $name,
    ) {
    }
}
