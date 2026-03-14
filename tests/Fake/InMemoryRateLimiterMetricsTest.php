<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryRateLimiterMetricsTest extends TestCase
{
    #[Test]
    public function recordLevelChangeStoresCall(): void
    {
        $metrics = new InMemoryRateLimiterMetrics();

        $metrics->recordLevelChange('gmail', 'decreased');

        self::assertCount(1, $metrics->getLevelChanges());
        self::assertSame(['provider' => 'gmail', 'direction' => 'decreased'], $metrics->getLevelChanges()[0]);
    }

    #[Test]
    public function recordWebhookEventStoresCall(): void
    {
        $metrics = new InMemoryRateLimiterMetrics();

        $metrics->recordWebhookEvent('delivery.completed', 'gmail');

        self::assertCount(1, $metrics->getWebhookEvents());
        self::assertSame(['eventType' => 'delivery.completed', 'provider' => 'gmail'], $metrics->getWebhookEvents()[0]);
    }

    #[Test]
    public function recordsMultipleCalls(): void
    {
        $metrics = new InMemoryRateLimiterMetrics();

        $metrics->recordLevelChange('gmail', 'decreased');
        $metrics->recordLevelChange('microsoft', 'increased');
        $metrics->recordWebhookEvent('delivery.completed', 'gmail');

        self::assertCount(2, $metrics->getLevelChanges());
        self::assertCount(1, $metrics->getWebhookEvents());
    }

    #[Test]
    public function resetClearsAll(): void
    {
        $metrics = new InMemoryRateLimiterMetrics();
        $metrics->recordLevelChange('gmail', 'decreased');
        $metrics->recordWebhookEvent('delivery.completed', 'gmail');

        $metrics->reset();

        self::assertCount(0, $metrics->getLevelChanges());
        self::assertCount(0, $metrics->getWebhookEvents());
    }

    #[Test]
    public function emptyByDefault(): void
    {
        $metrics = new InMemoryRateLimiterMetrics();

        self::assertCount(0, $metrics->getLevelChanges());
        self::assertCount(0, $metrics->getWebhookEvents());
    }
}
