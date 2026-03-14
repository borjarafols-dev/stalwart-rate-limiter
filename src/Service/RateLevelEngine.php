<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\RateLevelEngineInterface;
use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\ValueObject\RateTier;

final readonly class RateLevelEngine implements RateLevelEngineInterface
{
    private const int PERMANENT_FAILURE_THRESHOLD = 3;
    private const int TEMPORARY_FAILURE_THRESHOLD = 5;
    private const int RAMP_UP_SUCCESS_THRESHOLD = 50;
    private const int RAMP_UP_HOLD_HOURS = 24;
    private const int STALE_DAYS = 7;

    public function processEvent(ProviderState $state, bool $success, ?string $errorType = null): LevelChange
    {
        $now = new \DateTimeImmutable();
        $isStale = $this->isStale($state, $now);
        $state->setLastEvent($now);

        if ($isStale) {
            return $this->resetToDefault($state);
        }

        if ($success) {
            return $this->handleSuccess($state);
        }

        return $this->handleFailure($state, $errorType);
    }

    public function processStale(ProviderState $state): LevelChange
    {
        if (!$this->isStale($state, new \DateTimeImmutable())) {
            return LevelChange::None;
        }

        return $this->resetToDefault($state);
    }

    private function handleSuccess(ProviderState $state): LevelChange
    {
        $state->setSuccessCount($state->getSuccessCount() + 1);
        $state->setFailCount(0);

        if ($this->canPromote($state)) {
            $state->applyLevel($state->getCurrentLevel() + 1);
            $state->setSuccessCount(0);

            return LevelChange::Increased;
        }

        return LevelChange::None;
    }

    private function handleFailure(ProviderState $state, ?string $errorType): LevelChange
    {
        $state->setFailCount($state->getFailCount() + 1);
        $state->setSuccessCount(0);

        if ($this->shouldDemote($state, $errorType)) {
            $state->applyLevel($state->getCurrentLevel() - 1);
            $state->setFailCount(0);

            return LevelChange::Decreased;
        }

        return LevelChange::None;
    }

    private function canPromote(ProviderState $state): bool
    {
        if ($state->getCurrentLevel() >= RateTier::MAX_LEVEL) {
            return false;
        }

        if ($state->getSuccessCount() < self::RAMP_UP_SUCCESS_THRESHOLD) {
            return false;
        }

        $holdDuration = $state->getLastChange()->diff(new \DateTimeImmutable());

        return $holdDuration->days >= 1 || $holdDuration->h >= self::RAMP_UP_HOLD_HOURS;
    }

    private function shouldDemote(ProviderState $state, ?string $errorType): bool
    {
        if ($state->getCurrentLevel() <= RateTier::MIN_LEVEL) {
            return false;
        }

        return match ($errorType) {
            '5xx' => $state->getFailCount() >= self::PERMANENT_FAILURE_THRESHOLD,
            '4xx' => $state->getFailCount() >= self::TEMPORARY_FAILURE_THRESHOLD,
            default => false,
        };
    }

    private function isStale(ProviderState $state, \DateTimeImmutable $now): bool
    {
        $daysSinceLastEvent = $state->getLastEvent()->diff($now)->days;

        return $daysSinceLastEvent >= self::STALE_DAYS;
    }

    private function resetToDefault(ProviderState $state): LevelChange
    {
        $previousLevel = $state->getCurrentLevel();
        $state->applyLevel(RateTier::DEFAULT_LEVEL);
        $state->setFailCount(0);
        $state->setSuccessCount(0);

        if ($previousLevel > RateTier::DEFAULT_LEVEL) {
            return LevelChange::Decreased;
        }

        if ($previousLevel < RateTier::DEFAULT_LEVEL) {
            return LevelChange::Increased;
        }

        return LevelChange::None;
    }
}
