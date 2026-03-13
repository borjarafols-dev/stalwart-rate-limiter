<?php

declare(strict_types=1);

namespace App\Contract;

interface StalwartApiClientInterface
{
    /**
     * @return array<string, string>
     */
    public function getSettings(string $prefix): array;

    /**
     * @param array<string, string> $keyValues
     */
    public function setSettings(array $keyValues): void;

    public function deleteSettings(string $prefix): void;

    /**
     * @param list<string> $domains
     */
    public function applyRateLimit(string $provider, array $domains, string $rate, int $concurrency): void;
}
