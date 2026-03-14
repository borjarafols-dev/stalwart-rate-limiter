<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\Service\RateLevelEngine;
use App\ValueObject\RateTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLevelEngineTest extends TestCase
{
    private RateLevelEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new RateLevelEngine();
    }

    #[Test]
    public function successIncrementsSuccessCountAndResetsFailCount(): void
    {
        $state = new ProviderState('gmail');
        $state->setFailCount(2);

        $result = $this->engine->processEvent($state, true);

        self::assertSame(LevelChange::None, $result);
        self::assertSame(1, $state->getSuccessCount());
        self::assertSame(0, $state->getFailCount());
    }

    #[Test]
    public function failureIncrementsFailCountAndResetsSuccessCount(): void
    {
        $state = new ProviderState('gmail');
        $state->setSuccessCount(10);

        $result = $this->engine->processEvent($state, false, '5xx');

        self::assertSame(LevelChange::None, $result);
        self::assertSame(1, $state->getFailCount());
        self::assertSame(0, $state->getSuccessCount());
    }

    #[Test]
    public function threePermanentFailuresDemotesOneLevel(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(3);

        $this->engine->processEvent($state, false, '5xx');
        $this->engine->processEvent($state, false, '5xx');
        $result = $this->engine->processEvent($state, false, '5xx');

        self::assertSame(LevelChange::Decreased, $result);
        self::assertSame(2, $state->getCurrentLevel());
        self::assertSame(0, $state->getFailCount());
    }

    #[Test]
    public function fiveTemporaryFailuresDemotesOneLevel(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(4);

        for ($i = 0; $i < 4; ++$i) {
            $result = $this->engine->processEvent($state, false, '4xx');
            self::assertSame(LevelChange::None, $result);
        }

        $result = $this->engine->processEvent($state, false, '4xx');

        self::assertSame(LevelChange::Decreased, $result);
        self::assertSame(3, $state->getCurrentLevel());
    }

    #[Test]
    public function cannotDemoteBelowLevelZero(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(0);

        for ($i = 0; $i < 5; ++$i) {
            $result = $this->engine->processEvent($state, false, '5xx');
            self::assertSame(LevelChange::None, $result);
        }

        self::assertSame(0, $state->getCurrentLevel());
    }

    #[Test]
    public function fiftySuccessesWithTwentyFourHourHoldPromotes(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(2);

        // Simulate the level was set 25 hours ago
        $reflection = new \ReflectionClass($state);
        $lastChangeProp = $reflection->getProperty('lastChange');
        $lastChangeProp->setValue($state, new \DateTimeImmutable('-25 hours'));

        for ($i = 0; $i < 49; ++$i) {
            $result = $this->engine->processEvent($state, true);
            self::assertSame(LevelChange::None, $result);
        }

        $result = $this->engine->processEvent($state, true);

        self::assertSame(LevelChange::Increased, $result);
        self::assertSame(3, $state->getCurrentLevel());
        self::assertSame(0, $state->getSuccessCount());
    }

    #[Test]
    public function fiftySuccessesWithoutTwentyFourHourHoldDoesNotPromote(): void
    {
        $state = new ProviderState('gmail');

        for ($i = 0; $i < 50; ++$i) {
            $result = $this->engine->processEvent($state, true);
            self::assertSame(LevelChange::None, $result);
        }

        self::assertSame(2, $state->getCurrentLevel());
        self::assertSame(50, $state->getSuccessCount());
    }

    #[Test]
    public function cannotPromoteAboveMaxLevel(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(RateTier::MAX_LEVEL);

        $reflection = new \ReflectionClass($state);
        $lastChangeProp = $reflection->getProperty('lastChange');
        $lastChangeProp->setValue($state, new \DateTimeImmutable('-48 hours'));

        for ($i = 0; $i < 100; ++$i) {
            $result = $this->engine->processEvent($state, true);
            self::assertSame(LevelChange::None, $result);
        }

        self::assertSame(RateTier::MAX_LEVEL, $state->getCurrentLevel());
    }

    #[Test]
    public function staleStateResetsToDefaultLevel(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(5);
        $state->setLastEvent(new \DateTimeImmutable('-8 days'));

        $result = $this->engine->processEvent($state, true);

        self::assertSame(LevelChange::Decreased, $result);
        self::assertSame(RateTier::DEFAULT_LEVEL, $state->getCurrentLevel());
    }

    #[Test]
    public function staleStateAtLevelZeroResetsToDefaultAndReturnsIncreased(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(0);
        $state->setLastEvent(new \DateTimeImmutable('-8 days'));

        $result = $this->engine->processEvent($state, true);

        self::assertSame(LevelChange::Increased, $result);
        self::assertSame(RateTier::DEFAULT_LEVEL, $state->getCurrentLevel());
    }

    #[Test]
    public function staleStateAlreadyAtDefaultReturnsNone(): void
    {
        $state = new ProviderState('gmail');
        $state->setLastEvent(new \DateTimeImmutable('-8 days'));

        $result = $this->engine->processEvent($state, true);

        self::assertSame(LevelChange::None, $result);
        self::assertSame(RateTier::DEFAULT_LEVEL, $state->getCurrentLevel());
    }

    #[Test]
    public function processStaleWithStaleState(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(4);
        $state->setLastEvent(new \DateTimeImmutable('-10 days'));

        $result = $this->engine->processStale($state);

        self::assertSame(LevelChange::Decreased, $result);
        self::assertSame(RateTier::DEFAULT_LEVEL, $state->getCurrentLevel());
    }

    #[Test]
    public function processStaleWithFreshStateReturnsNone(): void
    {
        $state = new ProviderState('gmail');

        $result = $this->engine->processStale($state);

        self::assertSame(LevelChange::None, $result);
    }

    #[Test]
    public function unknownErrorTypeDoesNotDemote(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(3);

        for ($i = 0; $i < 10; ++$i) {
            $result = $this->engine->processEvent($state, false, 'unknown');
            self::assertSame(LevelChange::None, $result);
        }

        self::assertSame(3, $state->getCurrentLevel());
    }

    #[Test]
    public function nullErrorTypeDoesNotDemote(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(3);

        for ($i = 0; $i < 10; ++$i) {
            $result = $this->engine->processEvent($state, false);
            self::assertSame(LevelChange::None, $result);
        }

        self::assertSame(3, $state->getCurrentLevel());
    }

    #[Test]
    public function successAfterFailuresResetsFailCount(): void
    {
        $state = new ProviderState('gmail');

        $this->engine->processEvent($state, false, '5xx');
        $this->engine->processEvent($state, false, '5xx');
        self::assertSame(2, $state->getFailCount());

        $this->engine->processEvent($state, true);
        self::assertSame(0, $state->getFailCount());
        self::assertSame(1, $state->getSuccessCount());
    }

    #[Test]
    public function failureAfterSuccessesResetsSuccessCount(): void
    {
        $state = new ProviderState('gmail');

        for ($i = 0; $i < 10; ++$i) {
            $this->engine->processEvent($state, true);
        }

        self::assertSame(10, $state->getSuccessCount());

        $this->engine->processEvent($state, false, '5xx');
        self::assertSame(0, $state->getSuccessCount());
        self::assertSame(1, $state->getFailCount());
    }

    #[Test]
    public function processEventUpdatesLastEvent(): void
    {
        $state = new ProviderState('gmail');
        $oldEvent = new \DateTimeImmutable('-1 hour');
        $state->setLastEvent($oldEvent);

        $this->engine->processEvent($state, true);

        self::assertGreaterThan($oldEvent, $state->getLastEvent());
    }

    #[Test]
    public function demotionAppliesCorrectRateAndConcurrency(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(3);

        $this->engine->processEvent($state, false, '5xx');
        $this->engine->processEvent($state, false, '5xx');
        $this->engine->processEvent($state, false, '5xx');

        self::assertSame('0.3/1s', $state->getLastRate());
        self::assertSame(2, $state->getLastConcurrency());
    }

    #[Test]
    public function promotionAppliesCorrectRateAndConcurrency(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(2);

        $reflection = new \ReflectionClass($state);
        $lastChangeProp = $reflection->getProperty('lastChange');
        $lastChangeProp->setValue($state, new \DateTimeImmutable('-25 hours'));

        for ($i = 0; $i < 50; ++$i) {
            $this->engine->processEvent($state, true);
        }

        self::assertSame('0.8/1s', $state->getLastRate());
        self::assertSame(3, $state->getLastConcurrency());
    }

    #[Test]
    public function staleResetClearsCounters(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(4);
        $state->setFailCount(2);
        $state->setSuccessCount(30);
        $state->setLastEvent(new \DateTimeImmutable('-8 days'));

        $this->engine->processStale($state);

        self::assertSame(0, $state->getFailCount());
        self::assertSame(0, $state->getSuccessCount());
    }
}
