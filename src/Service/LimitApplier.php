<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderMapperInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\StalwartApiClientInterface;
use App\Entity\ProviderState;

final readonly class LimitApplier implements LimitApplierInterface
{
    public function __construct(
        private StalwartApiClientInterface $stalwartApiClient,
        private ProviderMapperInterface $providerMapper,
        private ProviderStateRepositoryInterface $providerStateRepository,
    ) {
    }

    public function apply(ProviderState $state): void
    {
        $domains = $this->providerMapper->domainsForProvider($state->getProvider());

        $this->stalwartApiClient->applyRateLimit(
            $state->getProvider(),
            $domains,
            $state->getLastRate(),
            $state->getLastConcurrency(),
        );
    }

    public function syncAll(): void
    {
        foreach ($this->providerStateRepository->findAllActive() as $state) {
            $this->apply($state);
        }
    }
}
