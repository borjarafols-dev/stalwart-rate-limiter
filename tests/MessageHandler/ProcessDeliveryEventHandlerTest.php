<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderMapperInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\RateLevelEngineInterface;
use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\Message\ProcessDeliveryEvent;
use App\MessageHandler\ProcessDeliveryEventHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProcessDeliveryEventHandlerTest extends TestCase
{
    private RateLevelEngineInterface&MockObject $rateLevelEngine;
    private ProviderMapperInterface&MockObject $providerMapper;
    private LimitApplierInterface&MockObject $limitApplier;
    private ProviderStateRepositoryInterface&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private ProcessDeliveryEventHandler $handler;

    protected function setUp(): void
    {
        $this->rateLevelEngine = $this->createMock(RateLevelEngineInterface::class);
        $this->providerMapper = $this->createMock(ProviderMapperInterface::class);
        $this->limitApplier = $this->createMock(LimitApplierInterface::class);
        $this->repository = $this->createMock(ProviderStateRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new ProcessDeliveryEventHandler(
            $this->rateLevelEngine,
            $this->providerMapper,
            $this->limitApplier,
            $this->repository,
            $this->entityManager,
        );
    }

    #[Test]
    public function successEventIncrementsCounterWithoutApplyingLimits(): void
    {
        $this->providerMapper->method('resolve')->with('gmail.com')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->with('gmail')->willReturn($state);
        $this->rateLevelEngine->method('processEvent')->willReturn(LevelChange::None);

        $this->limitApplier->expects(self::never())->method('apply');
        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessDeliveryEvent('delivery.completed', 'gmail.com'));
    }

    #[Test]
    public function dsnSuccessIsTreatedAsSuccess(): void
    {
        $this->providerMapper->method('resolve')->willReturn('microsoft');
        $state = new ProviderState('microsoft');
        $this->repository->method('findByProvider')->willReturn($state);

        $this->rateLevelEngine->expects(self::once())
            ->method('processEvent')
            ->with($state, true, null)
            ->willReturn(LevelChange::None);

        ($this->handler)(new ProcessDeliveryEvent('dsn.success', 'outlook.com'));
    }

    #[Test]
    public function failureWith5xxStatusClassifiedCorrectly(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);

        $this->rateLevelEngine->expects(self::once())
            ->method('processEvent')
            ->with($state, false, '5xx')
            ->willReturn(LevelChange::None);

        ($this->handler)(new ProcessDeliveryEvent('delivery.failed', 'gmail.com', '550'));
    }

    #[Test]
    public function failureWith4xxStatusClassifiedCorrectly(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);

        $this->rateLevelEngine->expects(self::once())
            ->method('processEvent')
            ->with($state, false, '4xx')
            ->willReturn(LevelChange::None);

        ($this->handler)(new ProcessDeliveryEvent('delivery.failed', 'gmail.com', '421'));
    }

    #[Test]
    public function failureWithUnknownStatusPassesNull(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);

        $this->rateLevelEngine->expects(self::once())
            ->method('processEvent')
            ->with($state, false, null)
            ->willReturn(LevelChange::None);

        ($this->handler)(new ProcessDeliveryEvent('delivery.failed', 'gmail.com', 'unknown'));
    }

    #[Test]
    public function failureWithoutStatusPassesNull(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);

        $this->rateLevelEngine->expects(self::once())
            ->method('processEvent')
            ->with($state, false, null)
            ->willReturn(LevelChange::None);

        ($this->handler)(new ProcessDeliveryEvent('delivery.failed', 'gmail.com'));
    }

    #[Test]
    public function levelChangeTriggersLimitApplier(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);
        $this->rateLevelEngine->method('processEvent')->willReturn(LevelChange::Decreased);

        $this->limitApplier->expects(self::once())->method('apply')->with($state);

        ($this->handler)(new ProcessDeliveryEvent('delivery.failed', 'gmail.com', '550'));
    }

    #[Test]
    public function levelIncreaseTriggersLimitApplier(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $state = new ProviderState('gmail');
        $this->repository->method('findByProvider')->willReturn($state);
        $this->rateLevelEngine->method('processEvent')->willReturn(LevelChange::Increased);

        $this->limitApplier->expects(self::once())->method('apply')->with($state);

        ($this->handler)(new ProcessDeliveryEvent('delivery.completed', 'gmail.com'));
    }

    #[Test]
    public function newProviderIsCreatedAndPersisted(): void
    {
        $this->providerMapper->method('resolve')->willReturn('newprovider');
        $this->repository->method('findByProvider')->willReturn(null);
        $this->rateLevelEngine->method('processEvent')->willReturn(LevelChange::None);

        $this->entityManager->expects(self::once())->method('persist')
            ->with(self::callback(fn (ProviderState $s): bool => 'newprovider' === $s->getProvider()));
        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessDeliveryEvent('delivery.completed', 'unknown.org'));
    }

    #[Test]
    public function existingProviderIsNotPersisted(): void
    {
        $this->providerMapper->method('resolve')->willReturn('gmail');
        $this->repository->method('findByProvider')->willReturn(new ProviderState('gmail'));
        $this->rateLevelEngine->method('processEvent')->willReturn(LevelChange::None);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessDeliveryEvent('delivery.completed', 'gmail.com'));
    }
}
