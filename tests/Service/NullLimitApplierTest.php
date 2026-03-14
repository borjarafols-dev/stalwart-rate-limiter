<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ProviderState;
use App\Service\NullLimitApplier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullLimitApplierTest extends TestCase
{
    #[Test]
    public function applyDoesNothing(): void
    {
        $applier = new NullLimitApplier();
        $state = new ProviderState('gmail');

        $applier->apply($state);

        self::assertSame(2, $state->getCurrentLevel());
    }
}
