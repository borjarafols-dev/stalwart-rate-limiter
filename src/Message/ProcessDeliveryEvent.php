<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessDeliveryEvent
{
    public function __construct(
        public string $type,
        public string $rcptDomain,
        public ?string $status = null,
    ) {
    }
}
