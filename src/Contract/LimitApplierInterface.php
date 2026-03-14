<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\ProviderState;

interface LimitApplierInterface
{
    public function apply(ProviderState $state): void;
}
