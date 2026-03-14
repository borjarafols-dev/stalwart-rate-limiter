<?php

declare(strict_types=1);

namespace App\Contract;

interface RateLimiterMetricsInterface
{
    public function recordLevelChange(string $provider, string $direction): void;

    public function recordWebhookEvent(string $eventType, string $provider): void;
}
