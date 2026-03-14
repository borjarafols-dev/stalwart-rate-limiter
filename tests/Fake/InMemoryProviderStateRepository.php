<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\ProviderStateRepositoryInterface;
use App\Entity\ProviderState;

final class InMemoryProviderStateRepository implements ProviderStateRepositoryInterface
{
    /** @var array<string, ProviderState> */
    private array $states = [];

    public function findByProvider(string $provider): ?ProviderState
    {
        return $this->states[$provider] ?? null;
    }

    /**
     * @return list<ProviderState>
     */
    public function findAllActive(): array
    {
        $cutoff = new \DateTimeImmutable('-30 days');

        return array_values(array_filter(
            $this->states,
            static fn (ProviderState $s): bool => $s->getLastEvent() >= $cutoff,
        ));
    }

    /**
     * @return list<ProviderState>
     */
    public function findStale(\DateTimeImmutable $cutoff): array
    {
        return array_values(array_filter(
            $this->states,
            static fn (ProviderState $s): bool => $s->getLastEvent() < $cutoff,
        ));
    }

    public function save(ProviderState $state): void
    {
        $this->states[$state->getProvider()] = $state;
    }

    /**
     * @return array<string, ProviderState>
     */
    public function getAll(): array
    {
        return $this->states;
    }

    public function reset(): void
    {
        $this->states = [];
    }
}
