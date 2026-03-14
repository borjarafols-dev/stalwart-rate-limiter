<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\RateLevelEngineInterface;
use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\Scheduler\StaleProviderCleanupTask;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StaleProviderCleanupTaskTest extends TestCase
{
    private ProviderStateRepositoryInterface&MockObject $repository;
    private RateLevelEngineInterface&MockObject $rateLevelEngine;
    private LimitApplierInterface&MockObject $limitApplier;
    private StaleProviderCleanupTask $task;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProviderStateRepositoryInterface::class);
        $this->rateLevelEngine = $this->createMock(RateLevelEngineInterface::class);
        $this->limitApplier = $this->createMock(LimitApplierInterface::class);

        $this->task = new StaleProviderCleanupTask(
            $this->repository,
            $this->rateLevelEngine,
            $this->limitApplier,
        );
    }

    #[Test]
    public function staleProviderIsResetAndLimitsApplied(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(4);
        $state->setLastEvent(new \DateTimeImmutable('-10 days'));

        $this->repository->method('findStale')->willReturn([$state]);
        $this->rateLevelEngine->method('processStale')->with($state)->willReturn(LevelChange::Decreased);

        $this->limitApplier->expects(self::once())->method('apply')->with($state);
        $this->repository->expects(self::once())->method('save')->with($state);

        ($this->task)();
    }

    #[Test]
    public function staleProviderAlreadyAtDefaultIsNotApplied(): void
    {
        $state = new ProviderState('gmail');
        $state->setLastEvent(new \DateTimeImmutable('-10 days'));

        $this->repository->method('findStale')->willReturn([$state]);
        $this->rateLevelEngine->method('processStale')->willReturn(LevelChange::None);

        $this->limitApplier->expects(self::never())->method('apply');
        $this->repository->expects(self::once())->method('save')->with($state);

        ($this->task)();
    }

    #[Test]
    public function noStaleProvidersDoesNothing(): void
    {
        $this->repository->method('findStale')->willReturn([]);

        $this->limitApplier->expects(self::never())->method('apply');
        $this->repository->expects(self::never())->method('save');

        ($this->task)();
    }

    #[Test]
    public function multipleStaleProvidersAreAllProcessed(): void
    {
        $gmail = new ProviderState('gmail');
        $gmail->applyLevel(5);
        $gmail->setLastEvent(new \DateTimeImmutable('-10 days'));

        $yahoo = new ProviderState('yahoo');
        $yahoo->applyLevel(0);
        $yahoo->setLastEvent(new \DateTimeImmutable('-10 days'));

        $this->repository->method('findStale')->willReturn([$gmail, $yahoo]);
        $this->rateLevelEngine->method('processStale')
            ->willReturnMap([
                [$gmail, LevelChange::Decreased],
                [$yahoo, LevelChange::Increased],
            ]);

        $this->limitApplier->expects(self::exactly(2))->method('apply');
        $this->repository->expects(self::exactly(2))->method('save');

        ($this->task)();
    }
}
