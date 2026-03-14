<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\ProviderState;
use App\Enum\LevelChange;

interface RateLevelEngineInterface
{
    public function processEvent(ProviderState $state, bool $success, ?string $errorType = null): LevelChange;

    public function processStale(ProviderState $state): LevelChange;
}
