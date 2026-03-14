<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\LimitApplierInterface;
use App\Entity\ProviderState;

final class InMemoryLimitApplier implements LimitApplierInterface
{
    /** @var list<ProviderState> */
    private array $appliedStates = [];

    private int $syncAllCallCount = 0;

    public function apply(ProviderState $state): void
    {
        $this->appliedStates[] = $state;
    }

    public function syncAll(): void
    {
        ++$this->syncAllCallCount;
    }

    /**
     * @return list<ProviderState>
     */
    public function getAppliedStates(): array
    {
        return $this->appliedStates;
    }

    public function getSyncAllCallCount(): int
    {
        return $this->syncAllCallCount;
    }

    public function reset(): void
    {
        $this->appliedStates = [];
        $this->syncAllCallCount = 0;
    }
}
