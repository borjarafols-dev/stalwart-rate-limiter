<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ProviderState;
use App\ValueObject\RateTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderStateTest extends TestCase
{
    #[Test]
    public function constructorSetsDefaults(): void
    {
        $state = new ProviderState('gmail');

        self::assertSame('gmail', $state->getProvider());
        self::assertSame(RateTier::DEFAULT_LEVEL, $state->getCurrentLevel());
        self::assertSame(0, $state->getFailCount());
        self::assertSame(0, $state->getSuccessCount());
        self::assertSame('0.3/1s', $state->getLastRate());
        self::assertSame(2, $state->getLastConcurrency());
        self::assertInstanceOf(\DateTimeImmutable::class, $state->getLastChange());
        self::assertInstanceOf(\DateTimeImmutable::class, $state->getLastEvent());
    }

    #[Test]
    public function applyLevelUpdatesRateAndConcurrency(): void
    {
        $state = new ProviderState('gmail');

        $state->applyLevel(5);

        self::assertSame(5, $state->getCurrentLevel());
        self::assertSame('3/1s', $state->getLastRate());
        self::assertSame(8, $state->getLastConcurrency());
    }

    #[Test]
    public function applyLevelUpdatesLastChange(): void
    {
        $state = new ProviderState('gmail');
        $originalChange = $state->getLastChange();

        usleep(1000);
        $state->applyLevel(3);

        self::assertGreaterThanOrEqual($originalChange, $state->getLastChange());
    }

    #[Test]
    public function setFailCountUpdatesValue(): void
    {
        $state = new ProviderState('gmail');

        $state->setFailCount(5);

        self::assertSame(5, $state->getFailCount());
    }

    #[Test]
    public function setSuccessCountUpdatesValue(): void
    {
        $state = new ProviderState('gmail');

        $state->setSuccessCount(42);

        self::assertSame(42, $state->getSuccessCount());
    }

    #[Test]
    public function setLastEventUpdatesValue(): void
    {
        $state = new ProviderState('gmail');
        $event = new \DateTimeImmutable('2026-01-01');

        $state->setLastEvent($event);

        self::assertSame($event, $state->getLastEvent());
    }

    #[Test]
    public function applyLevelToZero(): void
    {
        $state = new ProviderState('gmail');

        $state->applyLevel(0);

        self::assertSame(0, $state->getCurrentLevel());
        self::assertSame('0.05/1s', $state->getLastRate());
        self::assertSame(1, $state->getLastConcurrency());
    }

    #[Test]
    public function applyLevelThrowsOnInvalidLevel(): void
    {
        $state = new ProviderState('gmail');

        $this->expectException(\InvalidArgumentException::class);
        $state->applyLevel(6);
    }
}
