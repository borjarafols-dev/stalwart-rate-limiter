<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Entity\ProviderState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryLimitApplierTest extends TestCase
{
    #[Test]
    public function applyRecordsState(): void
    {
        $applier = new InMemoryLimitApplier();
        $state = new ProviderState('gmail');

        $applier->apply($state);

        self::assertCount(1, $applier->getAppliedStates());
        self::assertSame($state, $applier->getAppliedStates()[0]);
    }

    #[Test]
    public function applyRecordsMultipleStates(): void
    {
        $applier = new InMemoryLimitApplier();
        $state1 = new ProviderState('gmail');
        $state2 = new ProviderState('microsoft');

        $applier->apply($state1);
        $applier->apply($state2);

        self::assertCount(2, $applier->getAppliedStates());
    }

    #[Test]
    public function resetClearsRecordedStates(): void
    {
        $applier = new InMemoryLimitApplier();
        $applier->apply(new ProviderState('gmail'));

        $applier->reset();

        self::assertCount(0, $applier->getAppliedStates());
    }

    #[Test]
    public function getAppliedStatesReturnsEmptyByDefault(): void
    {
        $applier = new InMemoryLimitApplier();

        self::assertCount(0, $applier->getAppliedStates());
    }
}
