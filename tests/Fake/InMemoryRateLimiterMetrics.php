<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\RateLimiterMetricsInterface;

final class InMemoryRateLimiterMetrics implements RateLimiterMetricsInterface
{
    /** @var list<array{provider: string, direction: string}> */
    private array $levelChanges = [];

    /** @var list<array{eventType: string, provider: string}> */
    private array $webhookEvents = [];

    public function recordLevelChange(string $provider, string $direction): void
    {
        $this->levelChanges[] = ['provider' => $provider, 'direction' => $direction];
    }

    public function recordWebhookEvent(string $eventType, string $provider): void
    {
        $this->webhookEvents[] = ['eventType' => $eventType, 'provider' => $provider];
    }

    /**
     * @return list<array{provider: string, direction: string}>
     */
    public function getLevelChanges(): array
    {
        return $this->levelChanges;
    }

    /**
     * @return list<array{eventType: string, provider: string}>
     */
    public function getWebhookEvents(): array
    {
        return $this->webhookEvents;
    }

    public function reset(): void
    {
        $this->levelChanges = [];
        $this->webhookEvents = [];
    }
}
