<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\LimitApplierInterface;
use App\Entity\ProviderState;

final readonly class NullLimitApplier implements LimitApplierInterface
{
    public function apply(ProviderState $state): void
    {
    }
}
