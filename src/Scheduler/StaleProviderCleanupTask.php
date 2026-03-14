<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\RateLevelEngineInterface;
use App\Enum\LevelChange;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask('1 day', schedule: 'default')]
final readonly class StaleProviderCleanupTask
{
    public function __construct(
        private ProviderStateRepositoryInterface $providerStateRepository,
        private RateLevelEngineInterface $rateLevelEngine,
        private LimitApplierInterface $limitApplier,
    ) {
    }

    public function __invoke(): void
    {
        $cutoff = new \DateTimeImmutable('-7 days');

        foreach ($this->providerStateRepository->findStale($cutoff) as $state) {
            $change = $this->rateLevelEngine->processStale($state);

            if (LevelChange::None !== $change) {
                $this->limitApplier->apply($state);
            }

            $this->providerStateRepository->save($state);
        }
    }
}
