<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\ProviderState;

interface ProviderStateRepositoryInterface
{
    public function findByProvider(string $provider): ?ProviderState;

    /**
     * @return list<ProviderState>
     */
    public function findAllActive(): array;

    /**
     * @return list<ProviderState>
     */
    public function findStale(\DateTimeImmutable $cutoff): array;

    public function save(ProviderState $state): void;
}
