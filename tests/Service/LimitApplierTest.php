<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\ProviderMapperInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\StalwartApiClientInterface;
use App\Entity\ProviderState;
use App\Service\LimitApplier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LimitApplierTest extends TestCase
{
    private StalwartApiClientInterface&MockObject $stalwartApiClient;
    private ProviderMapperInterface&MockObject $providerMapper;
    private ProviderStateRepositoryInterface&MockObject $repository;
    private LimitApplier $applier;

    protected function setUp(): void
    {
        $this->stalwartApiClient = $this->createMock(StalwartApiClientInterface::class);
        $this->providerMapper = $this->createMock(ProviderMapperInterface::class);
        $this->repository = $this->createMock(ProviderStateRepositoryInterface::class);

        $this->applier = new LimitApplier(
            $this->stalwartApiClient,
            $this->providerMapper,
            $this->repository,
        );
    }

    #[Test]
    public function applyPushesCorrectConfigToStalwart(): void
    {
        $state = new ProviderState('gmail');
        $state->applyLevel(3);

        $this->providerMapper->method('domainsForProvider')
            ->with('gmail')
            ->willReturn(['gmail.com', 'googlemail.com']);

        $this->stalwartApiClient->expects(self::once())
            ->method('applyRateLimit')
            ->with('gmail', ['gmail.com', 'googlemail.com'], '0.8/1s', 3);

        $this->applier->apply($state);
    }

    #[Test]
    public function applyUsesCurrentStateRateAndConcurrency(): void
    {
        $state = new ProviderState('microsoft');
        $state->applyLevel(0);

        $this->providerMapper->method('domainsForProvider')
            ->willReturn(['outlook.com', 'hotmail.com']);

        $this->stalwartApiClient->expects(self::once())
            ->method('applyRateLimit')
            ->with('microsoft', ['outlook.com', 'hotmail.com'], '0.05/1s', 1);

        $this->applier->apply($state);
    }

    #[Test]
    public function applyWithDefaultProviderPassesEmptyDomains(): void
    {
        $state = new ProviderState('default');

        $this->providerMapper->method('domainsForProvider')
            ->with('default')
            ->willReturn([]);

        $this->stalwartApiClient->expects(self::once())
            ->method('applyRateLimit')
            ->with('default', [], '0.3/1s', 2);

        $this->applier->apply($state);
    }

    #[Test]
    public function syncAllAppliesAllActiveProviders(): void
    {
        $gmail = new ProviderState('gmail');
        $microsoft = new ProviderState('microsoft');

        $this->repository->method('findAllActive')
            ->willReturn([$gmail, $microsoft]);

        $this->providerMapper->method('domainsForProvider')
            ->willReturnMap([
                ['gmail', ['gmail.com', 'googlemail.com']],
                ['microsoft', ['outlook.com', 'hotmail.com', 'live.com', 'msn.com']],
            ]);

        $this->stalwartApiClient->expects(self::exactly(2))
            ->method('applyRateLimit');

        $this->applier->syncAll();
    }

    #[Test]
    public function syncAllWithNoActiveProvidersDoesNothing(): void
    {
        $this->repository->method('findAllActive')->willReturn([]);

        $this->stalwartApiClient->expects(self::never())->method('applyRateLimit');

        $this->applier->syncAll();
    }
}
