<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\LimitApplierInterface;
use App\Entity\ProviderState;

final class InMemoryLimitApplier implements LimitApplierInterface
{
    /** @var list<ProviderState> */
    private array $appliedStates = [];

    public function apply(ProviderState $state): void
    {
        $this->appliedStates[] = $state;
    }

    /**
     * @return list<ProviderState>
     */
    public function getAppliedStates(): array
    {
        return $this->appliedStates;
    }

    public function reset(): void
    {
        $this->appliedStates = [];
    }
}
