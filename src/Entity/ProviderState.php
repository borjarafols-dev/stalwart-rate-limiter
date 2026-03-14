<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProviderStateRepository;
use App\ValueObject\RateTier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProviderStateRepository::class)]
#[ORM\Table(name: 'provider_state')]
class ProviderState
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $provider;

    #[ORM\Column]
    private int $currentLevel;

    #[ORM\Column]
    private int $failCount;

    #[ORM\Column]
    private int $successCount;

    #[ORM\Column]
    private \DateTimeImmutable $lastChange;

    #[ORM\Column]
    private \DateTimeImmutable $lastEvent;

    #[ORM\Column(length: 16)]
    private string $lastRate;

    #[ORM\Column]
    private int $lastConcurrency;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
        $this->currentLevel = RateTier::DEFAULT_LEVEL;
        $this->failCount = 0;
        $this->successCount = 0;
        $this->lastChange = new \DateTimeImmutable();
        $this->lastEvent = new \DateTimeImmutable();

        $tier = RateTier::forLevel(RateTier::DEFAULT_LEVEL);
        $this->lastRate = $tier->rate;
        $this->lastConcurrency = $tier->concurrency;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getCurrentLevel(): int
    {
        return $this->currentLevel;
    }

    public function getFailCount(): int
    {
        return $this->failCount;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getLastChange(): \DateTimeImmutable
    {
        return $this->lastChange;
    }

    public function getLastEvent(): \DateTimeImmutable
    {
        return $this->lastEvent;
    }

    public function getLastRate(): string
    {
        return $this->lastRate;
    }

    public function getLastConcurrency(): int
    {
        return $this->lastConcurrency;
    }

    public function applyLevel(int $level): void
    {
        $tier = RateTier::forLevel($level);
        $this->currentLevel = $level;
        $this->lastRate = $tier->rate;
        $this->lastConcurrency = $tier->concurrency;
        $this->lastChange = new \DateTimeImmutable();
    }

    public function setFailCount(int $failCount): void
    {
        $this->failCount = $failCount;
    }

    public function setSuccessCount(int $successCount): void
    {
        $this->successCount = $successCount;
    }

    public function setLastEvent(\DateTimeImmutable $lastEvent): void
    {
        $this->lastEvent = $lastEvent;
    }
}
